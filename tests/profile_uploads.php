<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
define('AVELA_HAIR_TEST_LIBRARY', true);
require __DIR__ . '/hair_profiles.php';

function profileUpload(?array $client, string $name, string $bytes, mixed $token = null): array
{
    global $base;
    $boundary = 'Avela' . bin2hex(random_bytes(16));
    $tokenName = is_array($token) ? '_token[]' : '_token';
    $token = is_array($token) ? 'bad' : ($token ?? ($client['csrf'] ?? ''));
    $body = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"" . $tokenName . "\"\r\n\r\n" . $token
        . "\r\n--" . $boundary . "\r\nContent-Disposition: form-data; name=\"profile_image\"; filename=\"" . $name
        . "\"\r\nContent-Type: application/octet-stream\r\n\r\n" . $bytes . "\r\n--" . $boundary . "--\r\n";
    $headers = 'Connection: close' . "\r\nContent-Type: multipart/form-data; boundary=" . $boundary . "\r\n";
    if ($client) $headers .= 'Cookie: ' . $client['cookie'] . "\r\n";
    $response = file_get_contents($base . '/profile/image', false, stream_context_create(['http'=>[
        'method'=>'POST', 'content'=>$body, 'header'=>$headers, 'ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>15,
    ]]));
    preg_match('/ ([0-9]{3}) /', $http_response_header[0] ?? '', $m);
    return ['code'=>(int)($m[1] ?? 0), 'body'=>(string)$response];
}

try {
    $options = getopt('', ['expect-database:','expect-port:','expect-datadir:','base-url:','session-directory:','scratch-directory:','output:']);
    $pdo = App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@datadir datadir')->fetch();
    $base = $options['base-url'] ?? ''; $url = parse_url($base);
    $sessions = realpath($options['session-directory'] ?? '');
    $norm = static fn($s) => strtolower(rtrim(str_replace('\\', '/', $s), '/'));
    if (!preg_match('/^avela_.*test_[A-Za-z0-9_]+$/D', $target['db']) || ($options['expect-database'] ?? '') !== $target['db']
        || env('DB_HOST') !== '127.0.0.1' || (int)($options['expect-port'] ?? 0) !== (int)$target['port']
        || in_array((int)$target['port'], [3306,13307], true) || $norm($options['expect-datadir'] ?? '') !== $norm($target['datadir'])
        || env('APP_ENV') !== 'development' || !$sessions || str_starts_with($norm($sessions), $norm(dirname(__DIR__)) . '/')
        || ($url['host'] ?? '') !== '127.0.0.1' || in_array((int)($url['port'] ?? 80), [80,18080], true)) throw new RuntimeException('Disposable guard');
    ini_set('session.save_path', $sessions); $checks = []; $users = new App\Models\User();
    $name = 'photo_' . bin2hex(random_bytes(5));
    $id = $users->createCustomer(['username'=>$name,'email'=>$name.'@example.invalid','full_name'=>'Synthetic upload customer',
        'contact_no'=>'+639123456789','password_hash'=>password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT)]);
    $users->activate($id); $client = hairClient($id);
    $manager = (int)$pdo->query("SELECT id FROM users WHERE username='image_catalog_manager'")->fetchColumn();
    $admin = (int)$pdo->query("SELECT id FROM users WHERE username='image_admin'")->fetchColumn();
    if (!$manager || !$admin) throw new RuntimeException('Required disposable staff fixtures unavailable');
    $webp = file_get_contents(dirname(__DIR__).'/public/assets/images/products/demo/avela-ave-smp-002.webp');
    $png = file_get_contents(dirname(__DIR__).'/public/assets/images/avela-logo.png');
    foreach ([['photo.WEBP',$webp],['photo.png',$png],['folder/../photo.webp',$webp]] as [$filename,$bytes]) {
        $old = $users->profileFor($id)['profile_image']; $response = profileUpload($client,$filename,$bytes);
        $path = $users->profileFor($id)['profile_image'];
        verifyHair('Genuine multipart image receives generated filename / '.$filename, $response['code'] === 302
            && preg_match('~^uploads/profile/[a-f0-9]{32}\.(png|webp)$~D',$path) === 1 && is_file(dirname(__DIR__).'/public/'.$path));
        verifyHair('Committed replacement removes prior generated image', $old === null || !file_exists(dirname(__DIR__).'/public/'.$old));
        verifyHair('Stored image is served with image content', hairRequest('/'.$path)['code'] === 200);
    }
    $before = $users->profileFor($id)['profile_image'];
    $hugePng = $png; $hugePng = substr_replace($hugePng, pack('NN',6001,6001),16,8);
    foreach ([['photo.jpg',$webp],['photo.php',$webp],['photo.svg','<svg onload="alert(1)"></svg>'],
        ['photo.webp','<?php echo "bad";'],['photo.webp',''],['photo.webp',str_repeat('x',2*1024*1024+1)],['huge.png',$hugePng]] as [$filename,$bytes]) {
        $response = profileUpload($client,$filename,$bytes);
        verifyHair('Invalid, mismatched or oversized upload preserves existing photo / '.$filename, $response['code'] === 302
            && $users->profileFor($id)['profile_image'] === $before);
    }
    foreach (['bad',[]] as $token) verifyHair('Invalid/malformed CSRF rejects multipart upload', profileUpload($client,'photo.webp',$webp,$token)['code'] === 419);
    verifyHair('Guest cannot upload a profile photograph',profileUpload(null,'photo.webp',$webp)['code'] === 302);
    foreach ([$admin,$manager] as $staff) verifyHair('Staff cannot mutate a customer photo route',profileUpload(hairClient($staff),'photo.webp',$webp)['code'] === 403);
    $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$id]);
    verifyHair('Inactive customer loses photo access',profileUpload($client,'photo.webp',$webp)['code'] === 302);
    $pdo->prepare("UPDATE users SET status='active' WHERE id=?")->execute([$id]);
    $client=hairClient($id);$folder=dirname(__DIR__).'/public/uploads/profile';$policy=$folder.'/.htaccess';$saved=file_get_contents($policy);
    try { unlink($policy); $response=profileUpload($client,'photo.webp',$webp);
        verifyHair('Missing storage policy fails closed without changing photo',$response['code']===302&&$users->profileFor($id)['profile_image']===$before);
    } finally { file_put_contents($policy,$saved); }
    $service=new App\Services\ProfileImageService();
    verifyHair('Cleanup preserves unrelated approved image',$service->discard('assets/images/products/demo/avela-ave-smp-002.webp')&&is_file(dirname(__DIR__).'/public/assets/images/products/demo/avela-ave-smp-002.webp'));
    verifyHair('Cleanup cannot traverse outside profile directory',$service->discard('uploads/profile/../../.env')&&is_file(dirname(__DIR__).'/.env'));
    $result=['checks'=>$checks];if(isset($options['output']))file_put_contents($options['output'],json_encode($result,JSON_PRETTY_PRINT));
    ob_end_flush();exit(count(array_filter($checks,static fn($c)=>$c['status']==='FAIL'))?1:0);
} catch (Throwable $e) { fwrite(STDERR,'Profile-upload checks stopped: '.get_class($e).' / '.basename($e->getFile()).':'.$e->getLine()."; private values suppressed.\n"); exit(1); }
