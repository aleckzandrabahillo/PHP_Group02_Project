<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
require $root . '/app/Core/Env.php';
App\Core\Env::load($root . '/.env');
require $root . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require_once $file;
    }
});
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(apply|proof-stdin|send-code|actor|target|expect-host|expect-port|expect-database|expect-server|expect-server-id|expect-datadir)(?:=(.+))?$/D', $arg, $m)
            || isset($options[$m[1]]) || (in_array($m[1], ['apply','proof-stdin','send-code'], true) ? isset($m[2]) : !isset($m[2]))) {
            throw new DomainException('Supply each exact target guard once. Dry-run is the default.');
        }
        $options[$m[1]] = $m[2] ?? true;
    }
    if (isset($options['send-code']) && (isset($options['apply']) || isset($options['proof-stdin']))) throw new DomainException('Choose sending proof or applying recovery, not both.');
    $pdo = App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id server_id,@@datadir datadir')->fetch();
    $norm = static fn(string $s): string => strtolower(rtrim(str_replace('\\', '/', $s), '/'));
    if (($options['expect-host'] ?? null) !== env('DB_HOST')
        || ($options['expect-database'] ?? null) !== $target['db']
        || ($options['expect-port'] ?? null) !== (string) $target['port']
        || ($options['expect-port'] ?? null) !== (string) env('DB_PORT', '3306')
        || ($options['expect-server'] ?? null) !== $target['server']
        || ($options['expect-server-id'] ?? null) !== (string) $target['server_id']
        || !isset($options['expect-datadir']) || $norm($options['expect-datadir']) !== $norm($target['datadir'])) {
        throw new DomainException('Exact host, port, database, server, server-ID and data-directory guards must match.');
    }
    require $root.'/vendor/autoload.php';
    $users=new App\Models\User();$actor=$users->findByIdentifier((string)($options['actor']??''));$target=$users->findByIdentifier((string)($options['target']??''));
    if(!$actor||!$target||$actor['role']!=='admin'||$actor['status']!=='active')throw new DomainException('Recovery target/actor unavailable.');
    $logs=new App\Services\LogService();$otp=new App\Services\OtpService(new App\Services\MailService(),$logs);
    $service=new App\Services\AdministratorRecovery(new App\Services\MfaService($users,$otp,$logs),$otp,$logs);
    if(isset($options['send-code'])){$service->sendCode((int)$actor['id']);echo "Verification delivery requested; retrieve only your own code privately.\n";exit;}
    if(!isset($options['apply'])){echo "Dry-run: verified target and active Administrator; no account changed. Use the hidden terminal helper for proof.\n";exit;}
    if(!isset($options['proof-stdin'])||function_exists('stream_isatty')&&stream_isatty(STDIN))throw new DomainException('Use hidden terminal prompts, never command arguments.');
    $password=fgets(STDIN,1026);$code=fgets(STDIN,130);
    if($password===false||$code===false)throw new DomainException('Recovery proof missing.');
    $ok=$service->unlock((int)$actor['id'],(int)$target['id'],rtrim($password,"\r\n"),rtrim($code,"\r\n"));unset($password,$code);
    if(!$ok)throw new DomainException('Recovery proof could not be verified.');
    echo "Account unlocked with Administrator proof; status, password and factors unchanged.\n";
} catch (DomainException $e) {
    fwrite(STDERR, 'RECOVERY STOPPED: ' . $e->getMessage() . PHP_EOL); exit(1);
} catch (Throwable) {
    fwrite(STDERR, "RECOVERY FAILED: Check access and schema; private diagnostics suppressed.\n"); exit(1);
}
