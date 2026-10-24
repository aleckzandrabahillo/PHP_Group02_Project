<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
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

try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(send|to-sender|actor|expect-host|expect-port|expect-database|expect-server|expect-server-id|expect-datadir)(?:=(.+))?$/D', $arg, $m)
            || isset($options[$m[1]]) || (in_array($m[1],['send','to-sender'],true) ? isset($m[2]) : !isset($m[2]))) {
            throw new DomainException('Use exact identity guards and actor once; dry-run is default. No recipient or credential arguments are supported.');
        }
        $options[$m[1]] = $m[2] ?? true;
    }
    $pdo = App\Core\Database::connection();
    $target = $pdo->query('SELECT DATABASE() db,@@port port,@@hostname server,@@server_id server_id,@@datadir datadir')->fetch();
    $norm = static fn(string $s): string => strtolower(rtrim(str_replace('\\', '/', $s), '/'));
    if (($options['expect-host'] ?? null) !== env('DB_HOST')
        || ($options['expect-port'] ?? null) !== (string)env('DB_PORT', '3306')
        || ($options['expect-port'] ?? null) !== (string)$target['port']
        || ($options['expect-database'] ?? null) !== $target['db']
        || ($options['expect-server'] ?? null) !== $target['server']
        || ($options['expect-server-id'] ?? null) !== (string)$target['server_id']
        || !isset($options['expect-datadir']) || $norm($options['expect-datadir']) !== $norm($target['datadir'])) {
        throw new DomainException('Exact host, port, database, server, server-ID and data-directory guards must match.');
    }
    require $root . '/vendor/autoload.php';
    $result = (new App\Services\MailTransportCheck())->run((string)($options['actor'] ?? ''), isset($options['send']),isset($options['to-sender']));
    echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
} catch (DomainException|App\Services\MailDeliveryException $e) {
    fwrite(STDERR, 'MAIL CHECK STOPPED: ' . $e->getMessage() . PHP_EOL); exit(1);
} catch (Throwable) {
    fwrite(STDERR, "MAIL CHECK FAILED: Check access and schema privately. If sending was requested, the provider may already have accepted the message; verify before retrying. No account or OTP changes were requested.\n"); exit(1);
}
