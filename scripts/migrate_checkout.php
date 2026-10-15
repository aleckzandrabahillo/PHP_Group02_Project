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
        if (!preg_match('/^--(apply|expect-host|expect-port|expect-database|expect-server|expect-server-id|expect-datadir)(?:=(.+))?$/D', $arg, $m)
            || isset($options[$m[1]]) || ($m[1] === 'apply' ? isset($m[2]) : !isset($m[2]))) {
            throw new DomainException('Use each exact target guard once; --apply is optional. Dry-run is the default.');
        }
        $options[$m[1]] = $m[2] ?? true;
    }
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
    $table = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="orders"');
    $table->execute();
    if ($table->fetchColumn() !== 'InnoDB') throw new DomainException('The existing orders table must use InnoDB.');
    $columns = [
        'recipient_name_snapshot' => ['varchar',120,'VARCHAR(120) NULL'],
        'contact_email_snapshot' => ['varchar',190,'VARCHAR(190) NULL'],
        'contact_no_snapshot' => ['varchar',25,'VARCHAR(25) NULL'],
        'checkout_key' => ['char',64,'CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL'],
    ];
    $pending = [];
    $get = $pdo->prepare('SELECT DATA_TYPE,CHARACTER_MAXIMUM_LENGTH,IS_NULLABLE,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="orders" AND COLUMN_NAME=?');
    foreach ($columns as $name => [$type, $length, $sql]) {
        $get->execute([$name]);
        $found = $get->fetch();
        if (!$found) $pending[] = 'ADD COLUMN ' . $name . ' ' . $sql;
        elseif ($found['DATA_TYPE'] !== $type || (int) $found['CHARACTER_MAXIMUM_LENGTH'] !== $length || $found['IS_NULLABLE'] !== 'YES'
            || ($name === 'checkout_key' && $found['COLLATION_NAME'] !== 'ascii_bin')) {
            throw new DomainException('An existing checkout column differs from the reviewed definition. Stop for review.');
        }
    }
    $indexes = $pdo->query('SHOW INDEX FROM orders WHERE Key_name="uq_orders_checkout_key"')->fetchAll();
    if (!$indexes) $pending[] = 'ADD UNIQUE KEY uq_orders_checkout_key (checkout_key)';
    elseif (count($indexes) !== 1 || (int) $indexes[0]['Non_unique'] !== 0 || $indexes[0]['Column_name'] !== 'checkout_key') {
        throw new DomainException('An existing checkout index differs from the reviewed definition.');
    }
    if (isset($options['apply']) && $pending) $pdo->exec('ALTER TABLE orders ' . implode(', ', $pending));
    echo json_encode(['mode' => isset($options['apply']) ? 'apply' : 'dry-run', 'pending_additions' => count($pending),
        'applied' => isset($options['apply']) ? count($pending) : 0, 'rows_updated' => 0], JSON_THROW_ON_ERROR), PHP_EOL;
} catch (DomainException $e) { fwrite(STDERR, 'MIGRATION STOPPED: ' . $e->getMessage() . PHP_EOL); exit(1); }
catch (Throwable) { fwrite(STDERR, "MIGRATION FAILED: Check access and schema; private diagnostics suppressed.\n"); exit(1); }
