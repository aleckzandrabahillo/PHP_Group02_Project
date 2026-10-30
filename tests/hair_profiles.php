<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.cache_limiter', '');
require dirname(__DIR__) . '/scripts/assign_demo_images.php';

function verifyHair(string $label, bool $ok): void {
    global $checks;
    $checks[] = ['test' => $label, 'status' => $ok ? 'PASS' : 'FAIL'];
    echo ($ok ? 'PASS: ' : 'FAIL: ') . $label . PHP_EOL;
}
function hairClient(int $id): array {
    session_name((string) env('SESSION_NAME'));
    session_id(''); session_start();
    $user = (new App\Models\User())->findById($id);
    $_SESSION = ['auth_user' => ['id' => $id, 'role' => $user['role'], 'username' => $user['username'], 'email' => $user['email']],
        'last_activity' => time(), '_csrf' => bin2hex(random_bytes(32))];
    $client = ['cookie' => session_name() . '=' . session_id(), 'csrf' => $_SESSION['_csrf']];
    session_write_close();
    return $client;
}
function hairRequest(string $path, ?array $client = null, ?array $data = null): array {
    global $base;
    $headers = "Connection: close\r\n";
    if ($client) $headers .= 'Cookie: ' . $client['cookie'] . "\r\n";
    $options = ['method' => $data === null ? 'GET' : 'POST', 'ignore_errors' => true,
        'follow_location' => 0, 'timeout' => 15, 'header' => $headers];
    if ($data !== null) {
        $options['content'] = http_build_query($data);
        $options['header'] .= "Content-Type: application/x-www-form-urlencoded\r\n";
    }
    $body = file_get_contents($base . $path, false, stream_context_create(['http' => $options]));
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s([0-9]{3})\s/', $responseHeaders[0] ?? '', $match);
    return ['code' => (int) ($match[1] ?? 0), 'body' => (string) $body, 'headers' => $responseHeaders];
}
function hairForm(array $client): array {
    $response = hairRequest('/assessment?edit=1', $client);
    if ($response['code'] !== 200) throw new RuntimeException('Assessment fixture unavailable.');
    preg_match('/name="profile_version" value="([a-f0-9]{64})"/', $response['body'], $match);
    return ['_token' => $client['csrf'], 'profile_version' => $match[1] ?? '', 'hair_texture' => 'wavy',
        'scalp_type' => 'oily_scalp', 'hair_condition' => 'dry', 'concerns' => ['frizz', 'tangling'],
        'treatments' => ['colored'], 'concern_text' => 'My scalp is oily but my ends are dry.'];
}
function hairState(PDO $pdo): string {
    $tables = ['hair_profiles', 'hair_profile_tags', 'audit_logs'];
    $out = [];
    foreach ($tables as $table) {
        $rows = $pdo->query('SELECT * FROM ' . $table)->fetchAll();
        $hashes = array_map(static fn($row) => hash('sha256', json_encode($row)), $rows);
        sort($hashes); $out[$table] = $hashes;
    }
    return hash('sha256', json_encode($out));
}
final class HairAuditFaultPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if (str_starts_with($query, 'INSERT INTO audit_logs')) throw new PDOException('Synthetic audit failure.');
        return parent::prepare($query, $options);
    }
}

if (defined('AVELA_HAIR_TEST_LIBRARY')) return;

try {
    $options = getopt('', ['expect-database:', 'expect-port:', 'expect-datadir:', 'base-url:', 'session-directory:', 'output:']);
    $pdo = App\Core\Database::connection();
    $identity = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $norm = static fn($path) => strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    $base = $options['base-url'] ?? '';
    $url = parse_url($base);
    $sessions = realpath($options['session-directory'] ?? '');
    if (!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D', $identity['db'])
        || ($options['expect-database'] ?? '') !== $identity['db']
        || (int) ($options['expect-port'] ?? 0) !== (int) $identity['port'] || in_array((int) $identity['port'], [3306, 13307], true)
        || $norm($options['expect-datadir'] ?? '') !== $norm($identity['datadir']) || env('DB_HOST') !== '127.0.0.1'
        || env('APP_ENV') !== 'development' || !$sessions || str_starts_with($norm($sessions), $norm(dirname(__DIR__)) . '/')
        || ($url['host'] ?? '') !== '127.0.0.1' || in_array((int) ($url['port'] ?? 80), [80,18080], true)) {
        throw new RuntimeException('Disposable-only identity guards stopped.');
    }
    ini_set('session.save_path', $sessions);
    $checks = [];
    $users = new App\Models\User();
    $model = new App\Models\HairProfile();
    $fixtureIds = [];
    foreach (['hair_a', 'hair_b'] as $name) {
        $name .= '_' . bin2hex(random_bytes(5));
        $id = $users->createCustomer(['username' => $name, 'email' => $name . '@example.invalid',
            'full_name' => 'Synthetic Hair Customer', 'contact_no' => '+639123456789',
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
        $users->activate($id);
        $fixtureIds[] = $id;
    }
    [$a, $b] = $fixtureIds;
    $client = hairClient($a); $other = hairClient($b);
    $productsBefore = $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll();
    foreach (['/assessment', '/assessment?user_id=' . $a] as $path) verifyHair('Guest denied ' . $path, hairRequest($path)['code'] === 302);
    verifyHair('Guest POST denied', hairRequest('/assessment', null, [])['code'] === 302);
    foreach (['image_catalog_manager','image_admin'] as $name) {
        $staff = hairClient((int) $users->findByIdentifier($name)['id']);
        verifyHair($name . ' GET denied', hairRequest('/assessment', $staff)['code'] === 403);
        verifyHair($name . ' POST denied', hairRequest('/assessment', $staff, ['_token'=>$staff['csrf']])['code'] === 403);
    }
    $form = hairForm($client);
    verifyHair('Assessment form exists with CSRF and version', strlen($form['profile_version']) === 64);
    $before = hairState($pdo);
    verifyHair('Invalid CSRF rejected', hairRequest('/assessment', $client, array_replace($form, ['_token'=>'bad']))['code'] === 419 && hairState($pdo) === $before);
    verifyHair('Array CSRF rejected safely', hairRequest('/assessment', $client, array_replace($form, ['_token'=>['bad']]))['code'] === 419);
    foreach (['hair_texture'=>'invalid', 'concern_text'=>str_repeat('a',501), 'concerns'=>['unknown']] as $field=>$bad) {
        $response = hairRequest('/assessment', $client, array_replace($form, [$field=>$bad]));
        verifyHair('Validation rejects ' . $field, $response['code'] === 422 && hairState($pdo) === $before);
        verifyHair('Accessible validation summary ' . $field, str_contains($response['body'], 'data-error-summary'));
    }
    $response = hairRequest('/assessment', $client, $form);
    verifyHair('Valid profile created with redirect', $response['code'] === 303);
    $profile = $model->forUser($a);
    verifyHair('Saved scalp and lengths independent', $profile['scalp_type'] === 'oily_scalp' && $profile['hair_condition'] === 'dry');
    verifyHair('Canonical NLP tag saved', (bool) array_filter($profile['tags'], static fn($tag) => $tag['source']==='nlp' && $tag['code']==='oily_scalp'));
    verifyHair('Create audit persisted', (bool) $pdo->query("SELECT 1 FROM audit_logs WHERE action='hair_profile.create' AND actor_id=" . $a)->fetchColumn());
    verifyHair('Audit omits concern text', !$pdo->query("SELECT 1 FROM audit_logs WHERE details LIKE '%My scalp is oily%'")->fetchColumn());
    $otherPage = hairRequest('/assessment?user_id=' . $a . '&profile_id=' . $profile['id'], $other);
    verifyHair('GET ownership ignores supplied IDs', !str_contains($otherPage['body'], 'SAVED TO YOUR ACCOUNT'));
    $before = hairState($pdo);
    verifyHair('Stale form rejected', hairRequest('/assessment', $client, $form)['code'] === 409 && hairState($pdo) === $before);
    $update = hairForm($client); $update['concern_text'] = '<script>alert(1)</script> Hindi naman dry yung hair ko.';
    $update['hair_condition'] = 'balanced'; $update['concerns'] = [];
    $update['scalp_type'] = 'normal_scalp'; $update['hair_texture'] = 'straight';
    $update['user_id'] = $b; $update['profile_id'] = 999;
    verifyHair('Update succeeds ignoring submitted owner IDs', hairRequest('/assessment', $client, $update)['code'] === 303);
    $updated = $model->forUser($a);
    verifyHair('Update preserves same owner/profile ID', $updated['id'] === $profile['id'] && !$model->forUser($b));
    verifyHair('Old tags replaced; negation not positive', !(bool) array_filter($updated['tags'], static fn($tag) => $tag['code'] === 'dryness'));
    $view = hairRequest('/assessment?edit=1', $client);
    verifyHair('Submitted markup encoded', str_contains($view['body'], '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($view['body'], '<script>alert(1)</script>'));
    verifyHair('Profile response no-store', (bool) array_filter($view['headers'], static fn($h) => str_starts_with(strtolower($h),'cache-control:') && str_contains($h,'no-store')));
    $before = hairState($pdo);
    $fault = new HairAuditFaultPDO('mysql:host=127.0.0.1;port=' . env('DB_PORT') . ';dbname=' . env('DB_DATABASE') . ';charset=utf8mb4',
        env('DB_USERNAME'), env('DB_PASSWORD'), [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    $rolledBack = false;
    try { (new App\Models\HairProfile($fault))->save($b, $form, [], App\Models\HairProfile::version(null)); }
    catch (PDOException) { $rolledBack = true; }
    verifyHair('Audit failure rolls back profile and tags', $rolledBack && hairState($pdo) === $before);
    $rejected = false;
    try { $model->save($b, $form, [['type'=>'concern','code'=>'not-a-tag']], App\Models\HairProfile::version(null)); }
    catch (RuntimeException) { $rejected = true; }
    verifyHair('Missing required tag rolls back', $rejected && hairState($pdo) === $before);
    $pdo->prepare('UPDATE users SET status="inactive" WHERE id=?')->execute([$b]);
    $response = hairRequest('/assessment', $other);
    verifyHair('Inactive customer loses access', $response['code']===302);
    $pdo->prepare('UPDATE users SET status="active" WHERE id=?')->execute([$b]);
    verifyHair('Products preserved', $productsBefore === $pdo->query('SELECT * FROM products ORDER BY id')->fetchAll());
    foreach (['/shop','/shop?search=shampoo','/product?id=1'] as $path) verifyHair('Public regression ' . $path, hairRequest($path)['code']===200);
    foreach (['/assets/css/app.css' => 'text/css', '/assets/js/app.js' => 'javascript',
        '/assets/images/avela-logo.png' => 'image/png',
        '/assets/images/products/demo/avela-ave-smp-002.webp' => 'image/webp'] as $path => $mime) {
        $asset = hairRequest($path);
        verifyHair('Static asset delivered ' . $path, $asset['code'] === 200 && strlen($asset['body']) > 1000
            && (bool) array_filter($asset['headers'], static fn($header) => str_contains(strtolower($header), $mime)));
    }
    foreach (['/orders','/routine','/profile'] as $path) verifyHair('Customer regression ' . $path, hairRequest($path,$client)['code']===200);
    $bagRedirect=hairRequest('/cart',$client);
    verifyHair('Legacy cart opens the shared drawer', $bagRedirect['code']===302 && in_array('Location: /?bag=open',$bagRedirect['headers'],true));
    verifyHair('Cart fragment remains customer accessible',hairRequest('/cart?panel=1',$client)['code']===200);
    $failed = count(array_filter($checks, static fn($row) => $row['status']==='FAIL'));
    if (isset($options['output'])) file_put_contents($options['output'], json_encode($checks, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo (count($checks)-$failed) . ' PASS, ' . $failed . " FAIL\n";
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'TEST STOPPED: ' . get_class($e) . ' at line ' . $e->getLine() . "; private diagnostics suppressed.\n");
    exit(1);
}
