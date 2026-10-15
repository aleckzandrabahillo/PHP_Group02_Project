<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/scripts/assign_demo_images.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
$options = getopt('', ['expect-database:', 'expect-port:', 'expect-datadir:', 'output:']);
$checks = [];
function verify(string $name, bool $ok): void
{
    global $checks;
    $checks[] = ['test' => $name, 'status' => $ok ? 'PASS' : 'FAIL'];
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $name . PHP_EOL;
}
function runStaff(array $arguments, #[SensitiveParameter] string $password = ''): array
{
    $process = proc_open([PHP_BINARY, dirname(__DIR__) . '/scripts/create_staff.php', ...$arguments],
        [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes, dirname(__DIR__));
    if (!is_resource($process)) throw new RuntimeException('CLI unavailable.');
    fwrite($pipes[0], $password . PHP_EOL); fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    return ['exit' => proc_close($process), 'output' => $output];
}
function snapshot(PDO $pdo, string $table): array
{
    $result = [];
    foreach ($pdo->query('SELECT * FROM `' . $table . '`')->fetchAll() as $row) {
        $result[(int) ($row['id'] ?? $row['user_id'])] = hash('sha256', json_encode($row));
    }
    return $result;
}
try {
    $pdo = App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir dir')->fetch();
    $norm = static fn($value) => strtolower(rtrim(str_replace('\\', '/', $value), '/'));
    if (env('APP_ENV') !== 'development' || env('DB_HOST') !== '127.0.0.1'
        || !preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D', $target['db'])
        || ($options['expect-database'] ?? '') !== $target['db']
        || (int) ($options['expect-port'] ?? 0) !== (int) $target['port']
        || in_array((int) $target['port'], [3306,13307], true)
        || $norm($options['expect-datadir'] ?? '') !== $norm($target['dir'])) throw new RuntimeException('Disposable-only guard stopped.');
    $before = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $before[$table] = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll();
    }
    $prefix = 'staffcli_' . bin2hex(random_bytes(4));
    $password = 'Aa1!' . bin2hex(random_bytes(18));
    $accounts = [];
    foreach (['admin','catalog_manager'] as $role) {
        $username = $prefix . '_' . $role;
        $args = [$role, $username . '@example.test', $username, 'Synthetic Staff', '--password-stdin'];
        $result = runStaff($args, $password);
        verify($role . ' CLI succeeds without password output', $result['exit'] === 0 && !str_contains($result['output'], $password));
        $stmt = $pdo->prepare('SELECT u.*,s.full_name FROM users u JOIN staff_profiles s ON s.user_id=u.id WHERE u.username=?');
        $stmt->execute([$username]); $user = $stmt->fetch();
        verify($role . ' active role, profile and email MFA', $user && $user['role'] === $role && $user['status'] === 'active' && (int)$user['mfa_enabled'] === 1 && $user['full_name'] === 'Synthetic Staff');
        verify($role . ' password correctly hashed', $user && $user['password_hash'] !== $password && password_verify($password, $user['password_hash']));
        $accounts[] = $user;
        verify($role . ' duplicate rejected', runStaff($args, $password)['exit'] !== 0);
    }
    $baseArgs = ['catalog_manager', $prefix . '_new@example.test', $prefix . '_new', 'Synthetic Staff', '--password-stdin'];
    $cases = [
        'Customer role' => [0,'customer'], 'Invalid email' => [1,'invalid'],
        'Overlong email' => [1,str_repeat('a',185).'@example.test'],
        'Short username' => [2,'bad'], 'Overlong username' => [2,str_repeat('a',41)],
        'Empty full name' => [3,' '], 'Overlong full name' => [3,str_repeat('a',121)],
        'Control in full name' => [3,"Name\nOther"],
    ];
    foreach ($cases as $name => [$index,$value]) { $args=$baseArgs; $args[$index]=$value; verify($name . ' rejected',runStaff($args,$password)['exit']!==0); }
    foreach (['Empty'=>'','Weak'=>'short','Overlong'=>str_repeat('A',73),'Multiline'=>$password."\nother"] as $name=>$input) {
        $result=runStaff($baseArgs,$input); verify($name . ' password rejected without disclosure',$result['exit']!==0 && ($input==='' || !str_contains($result['output'],$input)));
    }
    verify('Legacy password arguments rejected',runStaff(['admin','test@example.test','legacy_user','forbidden-argument','Synthetic Staff'])['exit']!==0);
    verify('No invalid accounts created',(int)$pdo->query("SELECT COUNT(*) FROM users WHERE username LIKE '".$prefix."%' ")->fetchColumn()===2);
    foreach ($before as $table=>$rows) {
        if (in_array($table,['users','staff_profiles'],true)) {
            $after = snapshot($pdo,$table);
            $unchanged = true;
            foreach ($rows as $row) if (($after[(int)($row['id'] ?? $row['user_id'])] ?? '') !== hash('sha256',json_encode($row))) $unchanged=false;
            verify('Existing '.$table.' rows preserved',$unchanged);
        } else {
            verify($table.' untouched',$rows===$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll());
        }
    }
    unset($password,$accounts,$before);
    $fail=count(array_filter($checks,static fn($check)=>$check['status']==='FAIL'));
    $report=['pass'=>count($checks)-$fail,'fail'=>$fail,'checks'=>$checks];
    if(isset($options['output'])) file_put_contents($options['output'],json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['pass'=>$report['pass'],'fail'=>$fail]),PHP_EOL;
    exit($fail?1:0);
} catch (Throwable $e) {
    fwrite(STDERR,"Staff CLI test stopped; private diagnostics suppressed.\n"); exit(1);
}
