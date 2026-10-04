<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Core/Env.php';

\App\Core\Env::load(dirname(__DIR__) . '/.env');

function envValue(string $key, mixed $default = null): mixed
{
    return \App\Core\Env::get($key, $default);
}

if (!in_array('mysql', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "ERROR: PHP pdo_mysql is not enabled.\n");
    exit(1);
}

$host = (string) envValue('DB_HOST', '127.0.0.1');
$port = (string) envValue('DB_PORT', '3306');
$user = (string) envValue('DB_USERNAME', 'root');
$pass = (string) envValue('DB_PASSWORD', '');
$db = (string) envValue('DB_DATABASE', 'avela');

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $safeDb = preg_replace('/[^A-Za-z0-9_]/', '', $db);
    if ($safeDb !== $db || $safeDb === '') {
        throw new RuntimeException('DB_DATABASE may contain only letters, numbers, and underscores.');
    }

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$safeDb}`");

    $sql = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Could not read database/schema.sql.');
    }

    $sql = preg_replace('/CREATE DATABASE IF NOT EXISTS `avela`[^;]*;/i', '', $sql);
    $sql = preg_replace('/USE `avela`;/i', '', $sql);

    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $pdo->exec($statement);
        }
    }

    $logDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    if (!is_writable($logDir)) {
        throw new RuntimeException('storage/logs is not writable.');
    }

    echo "Avela database installed successfully.\n";
    echo "Database: {$db}\n";
    echo "Next: create staff accounts if needed, then start the local PHP server.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'INSTALL FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
