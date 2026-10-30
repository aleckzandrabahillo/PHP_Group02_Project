<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('AVELA_HAIR_TEST_LIBRARY', true);
require __DIR__ . '/hair_profiles.php';

try {
    $options = getopt('', ['expect-database:', 'expect-port:', 'expect-datadir:', 'base-url:', 'session-directory:', 'output:']);
    $pdo = App\Core\Database::connection();
    $identity = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $base = $options['base-url'] ?? '';
    $sessions = realpath($options['session-directory'] ?? '');
    $url = parse_url($base);
    $norm = static fn($p) => strtolower(rtrim(str_replace('\\', '/', $p), '/'));
    if (!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D', $identity['db'])
        || ($options['expect-database'] ?? '') !== $identity['db'] || env('DB_HOST') !== '127.0.0.1'
        || (int) ($options['expect-port'] ?? 0) !== (int) $identity['port'] || in_array((int) $identity['port'], [3306, 13307], true)
        || $norm($options['expect-datadir'] ?? '') !== $norm($identity['datadir']) || env('APP_ENV') !== 'development'
        || !$sessions || str_starts_with($norm($sessions), $norm(dirname(__DIR__)) . '/')
        || ($url['host'] ?? '') !== '127.0.0.1' || in_array((int) ($url['port'] ?? 80), [80, 18080], true)) {
        throw new RuntimeException('Disposable-only identity guards stopped.');
    }
    ini_set('session.save_path', $sessions);
    $checks = [];
    $users = new App\Models\User();
    $fixtureName='settings_'.bin2hex(random_bytes(6));
    $customer=$users->createCustomer(['username'=>$fixtureName,'email'=>$fixtureName.'@example.invalid','full_name'=>'Synthetic settings customer','contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $users->activate($customer);
    (new App\Models\HairProfile())->save($customer,['hair_texture'=>'wavy','scalp_type'=>'oily_scalp','hair_condition'=>'dry','concerns'=>['frizz'],'treatments'=>[],'concern_text'=>'Dry ends.'],[],App\Models\HairProfile::version(null));
    $client = hairClient((int) $customer);
    foreach (['/settings', '/settings/security'] as $path) {
        verifyHair('Guest settings denied: ' . $path, hairRequest($path)['code'] === 302);
        $page = hairRequest($path, $client);
        verifyHair('Customer settings accessible: ' . $path, $page['code'] === 200);
        verifyHair('Settings not cacheable: ' . $path, (bool) array_filter($page['headers'], static fn($h) => str_starts_with(strtolower($h), 'cache-control:') && str_contains($h, 'no-store')));
        foreach (['image_admin', 'image_catalog_manager'] as $name) {
            verifyHair($name . ' cannot use customer settings: ' . $path, hairRequest($path, hairClient((int) $users->findByIdentifier($name)['id']))['code'] === 403);
        }
    }
    $settings = hairRequest('/settings', $client)['body'];
    preg_match('/<div class="settings-layout">(.*?)<\/div>/s', $settings, $settingsBody);
    verifyHair('Settings contains real authenticator and password destinations', str_contains($settingsBody[1] ?? '', '/settings/security') && str_contains($settingsBody[1] ?? '', '/account/password') && !preg_match('~/(profile|favorites|orders|routine)~', $settingsBody[1] ?? ''));
    $security = hairRequest('/settings/security', $client)['body'];
    verifyHair('Security uses existing protected MFA form', str_contains($security, 'action="/account/mfa"') && str_contains($security, 'name="_token"'));
    verifyHair('Existing MFA URL remains compatible', hairRequest('/account/mfa', $client)['code'] === 200);
    verifyHair('Existing MFA mutation still rejects invalid CSRF', hairRequest('/account/mfa', $client, ['action' => 'begin', '_token' => 'invalid'])['code'] === 419);
    $profile = hairRequest('/profile', $client)['body'];
    verifyHair('Profile remains details and editing', str_contains($profile, 'Account details') && str_contains($profile, 'data-dialog-open="profile-edit-dialog"') && !str_contains($profile, 'Start authenticator setup'));
    $entry = hairRequest('/assessment', $client)['body'];
    verifyHair('Saved profile offers results and retake without clearing data', str_contains($entry,'Explore my recommendations') && str_contains($entry,'Retake assessment') && !str_contains($entry,'data-assessment-form'));
    $assessment = hairRequest('/assessment?edit=1', $client)['body'];
    verifyHair('Verbose instructional example removed', !str_contains($assessment, 'English or Taglish is fine'));
    verifyHair('Privacy note retained', str_contains($assessment, 'Leave out names, contact details and medical information.'));
    verifyHair('Five handoff assessment steps including review', substr_count($assessment, 'data-quiz-step') === 5);
    foreach (['hair_texture', 'scalp_type', 'hair_condition', 'concerns[]', 'treatments[]', 'concern_text', 'profile_version'] as $field) {
        verifyHair('Assessment input preserved: ' . $field, str_contains($assessment, 'name="' . $field . '"'));
    }
    $bagRoute=hairRequest('/cart',$client);
    verifyHair('Legacy bag URL opens the shared drawer',in_array($bagRoute['code'],[302,303],true)&&in_array('Location: /?bag=open',$bagRoute['headers'],true));
    $bagLanding=hairRequest('/?bag=open',$client);
    verifyHair('Bag landing keeps one primary heading and one native bag drawer',$bagLanding['code']===200&&preg_match_all('/<h1\b/',$bagLanding['body'])===1&&substr_count($bagLanding['body'],'id="avela-bag"')===1);
    foreach (['/profile', '/favorites', '/orders', '/recommendations', '/routine', '/assessment', '/settings', '/settings/security'] as $path) {
        $page = hairRequest($path, $client);
        verifyHair('One primary heading: ' . $path, $page['code'] === 200 && preg_match_all('/<h1\b/', $page['body']) === 1);
        verifyHair('Focused account page omits the marketing footer: ' . $path, !str_contains($page['body'], '<footer class="site-footer"'));
    }
    foreach (['/login', '/register', '/activate'] as $path) {
        $page = hairRequest($path);
        verifyHair('Guest authentication primary heading: ' . $path, $page['code'] === 200 && preg_match_all('/<h1\b/', $page['body']) === 1);
    }
    $registration = hairRequest('/register')['body'];
    $minimum = App\Services\SecurityPolicy::values()['password_min_length'];
    verifyHair('Checklist reads effective server minimum', str_contains($registration, 'data-password-minimum="' . $minimum . '"'));
    verifyHair('Five customer-facing checklist rules apply only to new password', preg_match_all('/\bdata-password-requirements\b/', $registration) === 1 && substr_count($registration, 'data-password-rule=') === 5 && !str_contains($registration,'72 UTF-8 bytes'));
    foreach (['uppercase','lowercase','numbers','special'] as $class) {
        $policyKey='password_min_'.$class;
        verifyHair('Integrated checklist retains server count: '.$class, str_contains($registration,'data-password-'.$class.'="'.App\Services\SecurityPolicy::values()[$policyKey].'"'));
    }
    foreach (['/login','/register','/activate','/verify-otp'] as $path) {
        $page=hairRequest($path);
        verifyHair('Authentication never exposes private mail-log instructions: '.$path, !str_contains($page['body'],'storage/logs') && !str_contains($page['body'],'private development mail log') && !str_contains($page['body'],'Local development:'));
    }
    verifyHair('Shared password helper included once',substr_count($registration,'js/password-policy.js')===1);
    verifyHair('Old duplicate password feedback not reintroduced',!str_contains(file_get_contents(dirname(__DIR__).'/public/assets/js/app.js'),'function passwordRequirementState('));
    $result = ['checks' => $checks, 'pass' => count(array_filter($checks, static fn($c) => $c['status'] === 'PASS')), 'fail' => count(array_filter($checks, static fn($c) => $c['status'] === 'FAIL'))];
    if (isset($options['output'])) file_put_contents($options['output'], json_encode($result, JSON_PRETTY_PRINT));
    ob_end_flush(); exit($result['fail'] ? 1 : 0);
} catch (Throwable) {
    fwrite(STDERR, 'Customer settings tests stopped: inspect disposable identity and private diagnostics. No private values printed.' . PHP_EOL);
    exit(1);
}
