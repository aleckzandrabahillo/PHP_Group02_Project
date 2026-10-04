<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

try {
    $pdo = Database::connection();
    $result = $pdo->query('SELECT 1')->fetchColumn();

    if ($result) {
        echo "OK: PHP and MySQL connection are working.\n";
        exit(0);
    }

    fwrite(STDERR, "Database query failed.\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAILED: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
