<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/scripts/assign_demo_images.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Manila'));

$checks = [];
function check(string $name, bool $ok): void
{
    global $checks;
    $checks[] = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL'];
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}
function digest(PDO $pdo, array $tables): array
{
    $result = [];
    foreach ($tables as $table) {
        if (!preg_match('/^[a-z_]+$/D', $table)) throw new RuntimeException();
        $rows = array_map(static fn($row) => hash('sha256', json_encode($row)), $pdo->query('SELECT * FROM ' . $table)->fetchAll());
        sort($rows); $result[$table] = hash('sha256', implode('', $rows));
    }
    return $result;
}
function codeFor(string $email): string
{
    foreach (array_reverse(file(dirname(__DIR__) . '/storage/logs/dev_mail.log')) as $line) {
        if (str_contains($line, 'TO: ' . $email . ' | PURPOSE: login') && preg_match('/OTP: (\d{6})/', $line, $m)) return $m[1];
    }
    throw new RuntimeException('Synthetic OTP unavailable.');
}
function auth(): App\Services\AuthService
{
    $logs = new App\Services\LogService();
    return new App\Services\AuthService(new App\Models\User(), new App\Services\OtpService(new App\Services\MailService(), $logs), $logs);
}
function login(array $account): array
{
    session_name((string) env('SESSION_NAME')); session_id(''); session_start();
    $old = session_id(); $result = auth()->attempt($account['username'], $account['password']);
    if (($result['next'] ?? '') === 'otp') $result = auth()->verifyPendingOtp(codeFor($account['email']));
    $client = ['cookie' => session_name() . '=' . session_id(), 'csrf' => App\Core\Csrf::token()];
    check('Normal ' . $account['role'] . ' login and session regeneration', $result['ok'] && session_id() !== $old);
    session_write_close(); return $client;
}
function http(string $path, ?array $client = null, ?array $data = null): array
{
    global $base;
    $headers = "Connection: close\r\n" . ($client ? 'Cookie: ' . $client['cookie'] . "\r\n" : '');
    $options = ['method' => $data === null ? 'GET' : 'POST', 'timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0, 'header' => $headers];
    if ($data !== null) { $options['content'] = http_build_query($data); $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n"; }
    $body = file_get_contents($base . $path, false, stream_context_create(['http' => $options]));
    $response = $http_response_header ?? []; preg_match('/\s(\d{3})\s/', $response[0] ?? '', $m);
    $location = ''; foreach ($response as $line) if (str_starts_with(strtolower($line), 'location:')) $location = trim(substr($line, 9));
    return ['code' => (int) ($m[1] ?? 0), 'body' => (string) $body, 'location' => $location];
}

try {
    $options = getopt('', ['expect-database:', 'expect-port:', 'expect-datadir:', 'base-url:', 'session-directory:', 'scratch-directory:', 'output:', 'worker:']);
    $pdo = App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();
    $norm = static fn($s) => strtolower(rtrim(str_replace('\\', '/', $s), '/'));
    $base = $options['base-url'] ?? ''; $url = parse_url($base);
    $sessions = realpath($options['session-directory'] ?? ''); $scratch = realpath($options['scratch-directory'] ?? '');
    if (!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D', $target['db']) || ($options['expect-database'] ?? '') !== $target['db']
        || (int) ($options['expect-port'] ?? 0) !== (int) $target['port'] || in_array((int) $target['port'], [3306, 13307], true)
        || $norm($options['expect-datadir'] ?? '') !== $norm($target['dir']) || env('DB_HOST') !== '127.0.0.1' || env('APP_ENV') !== 'development'
        || !$sessions || !$scratch || str_starts_with($norm($scratch), $norm(dirname(__DIR__)) . '/')
        || str_starts_with($norm($sessions), $norm(dirname(__DIR__)) . '/') || ($url['host'] ?? '') !== '127.0.0.1'
        || ($url['scheme'] ?? '') !== 'http' || in_array((int) ($url['port'] ?? 80), [80, 18080], true)) throw new RuntimeException('Disposable-only guard stopped.');
    ini_set('session.save_path', $sessions);
    check('Database and PHP clocks use the configured timezone', abs((int)$pdo->query('SELECT UNIX_TIMESTAMP(NOW())')->fetchColumn()-time())<5
        && $pdo->query('SELECT @@session.time_zone')->fetchColumn()===date('P'));
    $users = new App\Models\User(); $management = new App\Services\SecurityManagement();
    if (isset($options['worker'])) {
        $id = (int) $options['worker']; $user = $users->findById($id);
        if (!$user || !str_starts_with($user['username'], 'security_')) throw new RuntimeException();
        $users->recordFailure($id); exit(0);
    }
    $preserve = ['products', 'categories', 'tags', 'product_tags', 'orders', 'order_items', 'carts', 'cart_items', 'favorites', 'routines', 'routine_items', 'product_reviews', 'hair_profiles', 'hair_profile_tags'];
    $before = digest($pdo, $preserve);
    $existingUsers = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();
    $migration = new App\Services\SecurityMigration();
    $dry = $migration->run($pdo, false);
    check('Migration dry-run preserves business records', digest($pdo, $preserve) === $before && $dry['applied'] === 0);
    $migration->run($pdo, true);
    check('Migration apply and repeat preserve records', $migration->run($pdo, true)['applied'] === 0 && digest($pdo, $preserve) === $before);
    $pdo->exec('UPDATE security_settings SET setting_value="5" WHERE setting_key="max_login_attempts"');
    check('Administrator five-attempt setting is preserved', App\Services\SecurityPolicy::values()['max_login_attempts'] === 5);
    $prefix = 'security_' . bin2hex(random_bytes(4)); $accounts = [];
    foreach (['customer', 'catalog_manager', 'admin'] as $role) {
        $account = ['username' => $prefix . '_' . $role, 'email' => $prefix . '_' . $role . '@example.invalid', 'password' => 'Q1!' . bin2hex(random_bytes(18)), 'role' => $role];
        if ($role === 'customer') {
            $id = $users->createCustomer(['full_name' => 'Synthetic security customer', 'email' => $account['email'], 'username' => $account['username'], 'password_hash' => password_hash($account['password'], PASSWORD_DEFAULT), 'contact_no' => '+639170000000']);
            $users->activate($id);
        } else {
            $id = $users->createStaff($role, $account['email'], $account['username'], password_hash($account['password'], PASSWORD_DEFAULT), 'Synthetic security staff');
        }
        $accounts[$role] = $account + ['id' => $id];
    }
    $customer = $accounts['customer']; $admin = $accounts['admin']; $manager = $accounts['catalog_manager'];
    $management->savePolicy($admin['id'], ['max_login_attempts'=>3,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);
    foreach ([1, 2, 5, 10, 255] as $threshold) {
        $management->savePolicy($admin['id'], ['max_login_attempts'=>$threshold,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);
        for ($attempt=1; $attempt<=$threshold; $attempt++) $users->recordFailure($manager['id']);
        $strict=$users->findById($manager['id']);
        check('Configured threshold locks at '.$threshold, App\Models\User::isLocked($strict) && (int)$strict['failed_attempts']===$threshold);
        $management->unlock($admin['id'],$manager['id'],$strict['locked_until']);
        $audit=$pdo->query('SELECT actor_id,details FROM audit_logs WHERE action="security_policy_updated" ORDER BY id DESC LIMIT 1')->fetch();
        check('Threshold change is actor-attributed and audited', (int)$audit['actor_id']===$admin['id'] && json_decode($audit['details'],true)['after']['max_login_attempts']===$threshold);
    }
    foreach ([0,-1,256,['2'],'invalid',1.5,true] as $invalid) {
        $denied=false;try{$management->savePolicy($admin['id'],['max_login_attempts'=>$invalid,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);}catch(InvalidArgumentException){$denied=true;}
        check('Reject malformed threshold or counter overflow', $denied);
    }
    foreach ([$manager['id'],$customer['id']] as $actor) {
        $denied=false;try{$management->savePolicy($actor,['max_login_attempts'=>1,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);}catch(DomainException){$denied=true;}
        check('Non-administrator cannot change login threshold', $denied && App\Services\SecurityPolicy::values()['max_login_attempts']===255);
    }
    $management->savePolicy($admin['id'], ['max_login_attempts'=>3,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12]);
    session_name((string) env('SESSION_NAME')); session_start();
    for ($i = 1; $i <= 3; $i++) {
        $result = auth()->attempt($customer['username'], 'deliberately-incorrect');
        $row = $users->findById($customer['id']);
        check('Wrong password attempt ' . $i, !$result['ok'] && (int) $row['failed_attempts'] === $i && App\Models\User::isLocked($row) === ($i === 3));
    }
    $until = $row['locked_until'];
    check('One history row for the lock incident', (int) $pdo->query('SELECT COUNT(*) FROM locked_accounts WHERE user_id=' . $customer['id'])->fetchColumn() === 1);
    check('Correct password cannot bypass an active lock', !auth()->attempt($customer['username'], $customer['password'])['ok']);
    $users->recordFailure($customer['id']);
    check('Repeated failures do not extend or duplicate a lock', $users->findById($customer['id'])['locked_until'] === $until && (int) $pdo->query('SELECT COUNT(*) FROM locked_accounts WHERE user_id=' . $customer['id'])->fetchColumn() === 1);
    foreach ([$manager['id'], $customer['id']] as $actor) {
        $denied = false; try { $management->unlock($actor, $customer['id'], $until); } catch (DomainException) { $denied = true; }
        check('Role cannot unlock: ' . $users->findById($actor)['role'], $denied && App\Models\User::isLocked($users->findById($customer['id'])));
    }
    $denied = false; try { $management->unlock($admin['id'], $customer['id'], '2000-01-01 00:00:00'); } catch (DomainException) { $denied = true; }
    check('Stale unlock rejected', $denied && App\Models\User::isLocked($users->findById($customer['id'])));
    $pdo->exec("CREATE TRIGGER security_test_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic audit fault'");
    $denied = false; try { $management->unlock($admin['id'], $customer['id'], $until); } catch (Throwable) { $denied = true; }
    $pdo->exec('DROP TRIGGER security_test_audit_failure');
    check('Unlock rolls back if auditing fails', $denied && App\Models\User::isLocked($users->findById($customer['id'])));
    $management->unlock($admin['id'], $customer['id'], $until);
    check('Administrator unlock clears counter without forging a login', !$users->findById($customer['id'])['locked_until'] && (int) $users->findById($customer['id'])['failed_attempts'] === 0 && $users->findById($customer['id'])['last_login_at'] === null);
    check('Unlock history and audit recorded', (int) $pdo->query('SELECT COUNT(*) FROM locked_accounts WHERE user_id=' . $customer['id'] . ' AND unlocked_by=' . $admin['id'])->fetchColumn() === 1 && (int) $pdo->query('SELECT COUNT(*) FROM audit_logs WHERE action="account_unlocked" AND target_id=' . $customer['id'])->fetchColumn() === 1);
    $pdo->prepare('UPDATE users SET failed_attempts=3,locked_until=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE id=?')->execute([$customer['id']]);
    $users->recordFailure($customer['id']);
    check('Legacy expired non-persistent lock starts a fresh failure window', (int) $users->findById($customer['id'])['failed_attempts'] === 1 && $users->findById($customer['id'])['locked_until'] === null);
    $workers = [];
    for ($i=0; $i<5; $i++) {
        $command = [PHP_BINARY, __FILE__, ...array_slice($argv, 1), '--worker=' . $customer['id']];
        $pipes = []; $process = proc_open($command, [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
        fclose($pipes[0]); $workers[] = [$process, $pipes];
    }
    $ok = true;
    foreach ($workers as [$process, $pipes]) { stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $ok = proc_close($process) === 0 && $ok; }
    check('Concurrent failures create one incident and stop at three', $ok && (int) $users->findById($customer['id'])['failed_attempts'] === 3 && (int) $pdo->query('SELECT COUNT(*) FROM locked_accounts WHERE user_id=' . $customer['id'])->fetchColumn() === 2);
    $management->unlock($admin['id'], $customer['id'], $users->findById($customer['id'])['locked_until']);
    $pdo->prepare('UPDATE users SET failed_attempts=2 WHERE id=?')->execute([$customer['id']]);
    $pdo->exec("CREATE TRIGGER security_test_lock_failure BEFORE INSERT ON locked_accounts FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic lock-history fault'");
    $denied=false; try { $users->recordFailure($customer['id']); } catch (Throwable) { $denied=true; }
    $pdo->exec('DROP TRIGGER security_test_lock_failure');
    check('Lock counter rolls back if required history fails', $denied && (int)$users->findById($customer['id'])['failed_attempts']===2 && $users->findById($customer['id'])['locked_until']===null);
    $defaults = ['max_login_attempts'=>3,'lockout_minutes'=>15,'session_timeout_minutes'=>30,'password_min_length'=>12];
    foreach (['max_login_attempts'=>256,'lockout_minutes'=>0,'session_timeout_minutes'=>0,'password_min_length'=>11] as $key=>$bad) {
        $denied = false; try { $management->savePolicy($admin['id'], [$key=>$bad]+$defaults); } catch (InvalidArgumentException) { $denied = true; }
        check('Reject unsafe policy: ' . $key, $denied);
    }
    $management->savePolicy($admin['id'], $defaults);
    check('Strong password accepted', App\Core\Validator::passwordError($customer['password']) === null);
    foreach (['Aa1!short','abcdefghijklmnop','A1!'.str_repeat('x',70),'Aa1'.str_repeat(' ',15)] as $invalid) check('Reject malformed or weak password', App\Core\Validator::passwordError($invalid) !== null);
    $management->savePolicy($admin['id'], ['password_min_length'=>16]+$defaults);
    check('Configured length is enforced', App\Core\Validator::passwordError('Abcdefghij1!') !== null);
    $management->savePolicy($admin['id'], $defaults);
    session_write_close();
    $qa = login($admin); $qm = login($manager); $qc = login($customer);
    check('Admin security page renders active policy and history', http('/admin/security', $qa)['code'] === 200 && str_contains(http('/admin/security', $qa)['body'], 'Lock history'));
    $page=http('/admin/security',$qa)['body'];
    preg_match('/<input[^>]*name="max_login_attempts"[^>]*>/', $page, $thresholdInput);
    check('Administrator threshold supports existing counter capacity',isset($thresholdInput[0])&&str_contains($thresholdInput[0],'min="1"')&&str_contains($thresholdInput[0],'max="255"')&&!str_contains($thresholdInput[0],'readonly'));
    foreach ([$qm,$qc] as $client) {
        check('Non-admin security GET denied', http('/admin/security', $client)['code'] === 403);
        check('Non-admin security POST denied', http('/admin/security', $client, ['_token'=>$client['csrf'],'action'=>'save_policy']+$defaults)['code'] === 403);
    }
    check('Guest security mutation redirects to login', http('/admin/security', null, ['action'=>'save_policy']+$defaults)['location'] === '/login');
    check('Missing CSRF rejected', http('/admin/security', $qa, ['action'=>'save_policy']+$defaults)['code'] === 419);
    check('Invalid CSRF rejected', http('/admin/security', $qa, ['_token'=>'invalid','action'=>'save_policy']+$defaults)['code'] === 419);
    check('Array CSRF rejected safely', http('/admin/security', $qa, ['_token'=>['invalid'],'action'=>'save_policy']+$defaults)['code'] === 419);
    check('Valid security policy POST succeeds', http('/admin/security', $qa, ['_token'=>$qa['csrf'],'action'=>'save_policy']+$defaults)['location'] === '/admin/security');
    $historyInsert=$pdo->prepare('INSERT INTO locked_accounts (user_id,username_snapshot,failed_attempts,locked_at,locked_until) VALUES (?, ?, 3, NOW(), DATE_SUB(NOW(),INTERVAL 1 SECOND))');
    for($i=0;$i<21;$i++)$historyInsert->execute([$customer['id'],$customer['username']]);
    $pageOne=$management->history(); $pageTwo=$management->history((int)$pageOne[19]['id']);
    check('Lock-history cursor has no duplicates or skipped boundary', count($pageOne)===21 && $pageTwo[0]['id']===$pageOne[20]['id'] && !array_intersect(array_column(array_slice($pageOne,0,20),'id'),array_column($pageTwo,'id')));
    check('Malformed history cursor renders safely', http('/admin/security?before[]=invalid',$qa)['code']===200);
    $untrusted='<img src=x onerror=alert(1)>';
    $historyInsert->execute([$customer['id'],$untrusted]);
    $html=http('/admin/security',$qa)['body'];
    check('History usernames are output-encoded', str_contains($html,htmlspecialchars($untrusted,ENT_QUOTES,'UTF-8')) && !str_contains($html,$untrusted));
    check('Catalog manager still sees products and inventory', http('/catalog/products',$qm)['code']===200 && http('/catalog/inventory',$qm)['code']===200);
    check('Customer catalog access remains denied', http('/catalog/products',$qc)['code']===403);
    $management->savePolicy($admin['id'], ['session_timeout_minutes'=>1]+$defaults);
    session_id(substr($qm['cookie'], strpos($qm['cookie'],'=')+1)); session_start(); $_SESSION['last_activity']=time()-70; session_write_close();
    check('Configured inactivity timeout revokes access', http('/catalog/products',$qm)['location']==='/login');
    $management->savePolicy($admin['id'], $defaults);
    session_id(''); session_start();
    $pdo->prepare('UPDATE otp_codes SET created_at=DATE_SUB(NOW(),INTERVAL 61 SECOND) WHERE user_id=?')->execute([$manager['id']]);
    $result=auth()->attempt($manager['username'],$manager['password']); $count=(int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$manager['id'])->fetchColumn();
    $result=auth()->attempt($manager['username'],$manager['password']);
    check('Unenrolled staff repeated login sends no email challenge', $result['ok'] && $result['next']==='dashboard' && (int)$pdo->query('SELECT COUNT(*) FROM otp_codes WHERE user_id='.$manager['id'])->fetchColumn()===$count);
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$manager['id']]);
    App\Core\Session::put('pending_flow',['user_id'=>$manager['id'],'purpose'=>'login']);
    check('Inactive staff cannot finish a pending OTP', !auth()->verifyPendingOtp('000000')['ok']);
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$manager['id']]);
    $_SESSION['profile_update_flow']=['private'=>'synthetic']; $_SESSION['checkout_intent']=['synthetic'=>true]; App\Core\Csrf::token();
    auth()->logout();
    check('Logout clears pending flows, Checkout and CSRF', array_intersect_key($_SESSION,array_flip(['pending_flow','profile_update_flow','checkout_intent','_csrf']))===[]);
    session_write_close();
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$customer['id']]);
    check('Customer made inactive loses protected access', http('/profile',$qc)['location']==='/login');
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$customer['id']]);
    check('Missing CAPTCHA token rejected', !(new App\Services\RecaptchaService())->verify(''));
    $mailEnv=$scratch.'/security-mail-policy.env';
    foreach (['APP_ENV=production\nMAIL_DRIVER=log','APP_ENV=development\nMAIL_DRIVER=unknown'] as $config) {
        file_put_contents($mailEnv,str_replace('\\n',"\n",$config)); App\Core\Env::load($mailEnv);
        $denied=false; try { (new App\Services\MailService())->sendOtp('synthetic@example.invalid','123456','login'); } catch (RuntimeException) { $denied=true; }
        check('Unsafe mail driver combination fails closed', $denied);
    }
    App\Core\Env::load(dirname(__DIR__).'/.env'); unlink($mailEnv);
    check('Unrelated catalog, inventory, carts and orders preserved', digest($pdo,$preserve)===$before);
    $oldUserRows=$pdo->query('SELECT * FROM users WHERE id < '.min(array_column($accounts,'id')).' ORDER BY id')->fetchAll();
    check('All pre-existing accounts preserved exactly', $existingUsers===$oldUserRows);
    file_put_contents($scratch.'/security-browser-private.json',json_encode(['accounts'=>$accounts],JSON_THROW_ON_ERROR));
    $failed=count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'));
    if(isset($options['output']))file_put_contents($options['output'],json_encode(['passed'=>count($checks)-$failed,'failed'=>$failed,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['passed'=>count($checks)-$failed,'failed'=>$failed]),PHP_EOL;
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    fwrite(STDERR,'SECURITY TESTS STOPPED: '.get_class($e).' at line '.$e->getLine().'. Private details suppressed.'.PHP_EOL); exit(1);
}
