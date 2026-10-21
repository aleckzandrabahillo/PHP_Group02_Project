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
    $favorites = new App\Models\Favorite();
    $fixtureIds = [];
    foreach (['ux_a', 'ux_b'] as $prefix) {
        $name = $prefix . '_' . bin2hex(random_bytes(5));
        $id = $users->createCustomer(['username' => $name, 'email' => $name . '@example.invalid',
            'full_name' => 'Synthetic UX Customer', 'contact_no' => '+639123456789',
            'password_hash' => password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
        $users->activate($id); $fixtureIds[] = $id;
    }
    [$a, $b] = $fixtureIds;
    $client = hairClient($a); $other = hairClient($b);
    $productIds = $pdo->query('SELECT p.id FROM products p JOIN categories c ON c.id=p.category_id WHERE p.status="active" AND c.status="active" ORDER BY p.id LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
    [$first, $second] = array_map('intval', $productIds);
    $pages = ['/', '/shop', '/product?id=' . $first, '/favorites', '/profile', '/assessment', '/recommendations', '/routine'];
    $countOnPage = static function (string $html, int $count): bool {
        return preg_match('/data-favorite-count\s+([^>]*)>' . $count . '<\/span>/', $html) === 1;
    };
    $getState = static fn(array $c) => json_decode(hairRequest('/favorites/state', $c)['body'], true);
    verifyHair('Guest cannot read favorite state', hairRequest('/favorites/state')['code'] === 302);
    verifyHair('Guest cannot mutate favorites', hairRequest('/favorites/toggle', null, [])['code'] === 302);
    verifyHair('New customer favorite count starts at zero', $getState($client)['count'] === 0);
    foreach ($pages as $path) verifyHair('Initial count rendered on ' . $path, $countOnPage(hairRequest($path, $client)['body'], 0));
    foreach ([$first, $second] as $productId) {
        $response = hairRequest('/favorites/toggle', $client, ['_token' => $client['csrf'], 'product_id' => $productId]);
        verifyHair('Favorite persisted for product ' . $productId, $response['code'] === 302 && in_array($productId, $favorites->idsForUser($a), true));
    }
    foreach ($pages as $path) {
        $page = hairRequest($path, $client);
        verifyHair('Persistent count after navigation to ' . $path, $countOnPage($page['body'], 2));
    }
    verifyHair('Product heart state rendered from stored IDs', str_contains(hairRequest('/product?id=' . $first, $client)['body'], 'aria-pressed="true"'));
    verifyHair('Refresh retains count', $countOnPage(hairRequest('/shop', $client)['body'], 2));
    verifyHair('Favorite state is private and not cacheable', (bool) array_filter(hairRequest('/favorites/state', $client)['headers'], static fn($h) => str_starts_with(strtolower($h), 'cache-control:') && str_contains($h, 'no-store')));
    verifyHair('Other customer count remains zero', $getState($other)['count'] === 0);
    verifyHair('Forged user ID cannot expose another customer favorites', json_decode(hairRequest('/favorites/state?user_id=' . $a, $other)['body'], true)['count'] === 0);
    foreach (['image_catalog_manager', 'image_admin'] as $staffName) {
        $staff = hairClient((int) $users->findByIdentifier($staffName)['id']);
        verifyHair($staffName . ' cannot read customer favorite state', hairRequest('/favorites/state', $staff)['code'] === 403);
        verifyHair($staffName . ' cannot mutate customer favorites', hairRequest('/favorites/toggle', $staff, ['_token' => $staff['csrf'], 'product_id' => $first])['code'] === 403);
    }
    verifyHair('Invalid favorite CSRF denied', hairRequest('/favorites/toggle', $client, ['_token' => 'invalid', 'product_id' => $first])['code'] === 419 && $favorites->countForUser($a) === 2);
    verifyHair('Array favorite CSRF safely denied', hairRequest('/favorites/toggle', $client, ['_token' => ['bad'], 'product_id' => $first])['code'] === 419);
    hairRequest('/favorites/toggle', $client, ['_token' => $client['csrf'], 'product_id' => $first]);
    verifyHair('Removal returns persisted count one', $getState($client)['count'] === 1 && $countOnPage(hairRequest('/', $client)['body'], 1));
    hairRequest('/favorites/toggle', $client, ['_token' => $client['csrf'], 'product_id' => $second]);
    verifyHair('Removing last favorite gives zero without negative counts', $getState($client)['count'] === 0 && $countOnPage(hairRequest('/favorites', $client)['body'], 0));

    $overview = hairRequest('/profile', $client);
    verifyHair('Profile defaults to read-only overview', str_contains($overview['body'], 'Your profile') && !str_contains($overview['body'], 'class="form-stack customer-profile-form"'));
    verifyHair('Overview provides explicit edit action', str_contains($overview['body'], '/profile?edit=1'));
    verifyHair('Profile links to settings without enrollment controls', str_contains($overview['body'], '/settings') && !str_contains($overview['body'], 'Start authenticator setup'));
    $edit = hairRequest('/profile?edit=1', $client);
    verifyHair('Explicit edit shows save and cancel', str_contains($edit['body'], 'Save changes') && str_contains($edit['body'], '>Cancel</a>'));
    verifyHair('Editing retains CSRF and email verification explanation', str_contains($edit['body'], 'name="_token"') && str_contains($edit['body'], 'current email address'));
    $before = $users->profileFor($a);
    $form = ['_token' => $client['csrf'], 'full_name' => $before['full_name'], 'email' => $before['email'], 'contact_no' => $before['contact_no'], 'delivery_address' => 'Synthetic delivery address'];
    foreach (['full_name' => '', 'email' => 'invalid', 'contact_no' => '<script>', 'delivery_address' => str_repeat('a', 501), 'full_name_array' => ['bad']] as $field => $bad) {
        $target = $field === 'full_name_array' ? 'full_name' : $field;
        $response = hairRequest('/profile/update', $client, array_replace($form, [$target => $bad]));
        verifyHair('Invalid profile ' . $field . ' returns to edit mode', $response['code'] === 302 && in_array('Location: /profile?edit=1', $response['headers'], true));
        $errorPage = hairRequest('/profile?edit=1', $client);
        verifyHair('Invalid profile ' . $field . ' has actionable errors', str_contains($errorPage['body'], 'data-error-summary') && ($field==='delivery_address'||str_contains($errorPage['body'], 'aria-invalid="true"')) && $users->profileFor($a) === $before);
    }
    hairRequest('/profile/update', $client, array_replace($form, ['full_name' => '<script>alert("draft")</script>', 'email' => 'invalid']));
    $encoded = hairRequest('/profile?edit=1', $client);
    verifyHair('Submitted profile text is retained with safe encoding', str_contains($encoded['body'], '&lt;script&gt;') && !str_contains($encoded['body'], 'value="<script>'));
    verifyHair('Rejected draft leaves stored profile unchanged', $users->profileFor($a) === $before);
    $response = hairRequest('/profile/update', $client, array_replace($form, ['_token' => 'invalid']));
    verifyHair('Profile invalid CSRF changes nothing', $response['code'] === 419 && $users->profileFor($a) === $before);
    $form['full_name'] = 'Updated Synthetic UX Customer';
    $form['email']='changed_'.bin2hex(random_bytes(5)).'@example.invalid';
    $response = hairRequest('/profile/update', $client, $form);
    verifyHair('Sensitive email edit starts existing OTP verification', $response['code'] === 302 && in_array('Location: /profile/verify-otp', $response['headers'], true));
    verifyHair('No account details saved before verification', $users->profileFor($a) === $before);
    $matches = [];
    foreach (file(dirname(__DIR__) . '/storage/logs/dev_mail.log', FILE_IGNORE_NEW_LINES) as $line) {
        if (str_contains($line, 'TO: ' . $before['email'] . ' | PURPOSE: profile_update |') && preg_match('/OTP: (\d{6}) /', $line, $m)) $matches = $m;
    }
    verifyHair('Synthetic profile OTP delivered privately', (bool) $matches);
    if (!$matches) throw new RuntimeException('Synthetic OTP missing.');
    $done = hairRequest('/profile/verify-otp', $client, ['_token' => $client['csrf'], 'otp' => $matches[1]]); unset($matches);
    verifyHair('Verified edits saved through existing process', $done['code'] === 302 && $users->profileFor($a)['full_name'] === $form['full_name']);
    verifyHair('Success returns to overview', !str_contains(hairRequest('/profile', $client)['body'], 'class="form-stack customer-profile-form"'));

    $logout = hairRequest('/logout', $client, ['_token' => $client['csrf']]);
    foreach ($logout['headers'] as $header) {
        if (preg_match('/^Set-Cookie: (' . preg_quote((string) env('SESSION_NAME'), '/') . '=[^;]+);/i', $header, $cookie)) $client['cookie'] = $cookie[1];
    }
    $notice = hairRequest('/login', $client);
    verifyHair('Sign-out feedback explicitly marked transient', str_contains($notice['body'], 'data-flash-transient') && str_contains($notice['body'], 'You have been signed out.'));
    verifyHair('Consumed sign-out notice does not recur on refresh', !str_contains(hairRequest('/login', $client)['body'], 'You have been signed out.'));
    $shop = hairRequest('/shop');
    $secondPage=hairRequest('/shop?page=2',$client);
    verifyHair('All twenty catalog products remain available across pages', substr_count($shop['body'], 'class="product-card"') + substr_count($secondPage['body'], 'class="product-card"') === 20);
    preg_match('~<nav class="category-tabs"[^>]*>(.*?)</nav>~s', $shop['body'], $categoryNav);
    preg_match_all('~href="([^"]+)"~', $categoryNav[1] ?? '', $categoryHrefs);
    $linkedCategories=[];
    foreach($categoryHrefs[1] as $href){
        parse_str(parse_url(html_entity_decode($href, ENT_QUOTES, 'UTF-8'), PHP_URL_QUERY) ?? '', $params);
        foreach($params['category'] ?? [] as $id)$linkedCategories[]=(int)$id;
    }
    sort($linkedCategories);
    $activeCategories=array_map('intval',$pdo->query('SELECT id FROM categories WHERE status="active" ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
    verifyHair('All six category links remain available', count($linkedCategories) === 6 && $linkedCategories === $activeCategories);
    $result = ['checks' => $checks, 'pass' => count(array_filter($checks, static fn($c) => $c['status'] === 'PASS')), 'fail' => count(array_filter($checks, static fn($c) => $c['status'] === 'FAIL'))];
    if (isset($options['output'])) file_put_contents($options['output'], json_encode($result, JSON_PRETTY_PRINT));
    ob_end_flush(); exit($result['fail'] ? 1 : 0);
} catch (Throwable) {
    fwrite(STDERR, 'Customer UX tests stopped. Check disposable identity and private diagnostics. No credentials printed.' . PHP_EOL);
    exit(1);
}
