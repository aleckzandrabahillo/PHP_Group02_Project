<?php
declare(strict_types=1);

/**
 * AVELA PayMongo migration.
 *
 * Default: dry-run only.
 * Apply only after review and explicit approval.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Core\Env;

function stopMigration(string $message): never
{
    fwrite(STDERR, "MIGRATION STOPPED: {$message}\n");
    exit(1);
}

$options = getopt('', [
    'apply',
    'expect-host:',
    'expect-port:',
    'expect-database:',
    'expect-server:',
    'expect-server-id:',
    'expect-datadir:',

    'db-host:',
    'db-port:',
    'db-name:',
    'db-user:',
]);

$required = [
    'expect-host',
    'expect-port',
    'expect-database',
    'expect-server',
    'expect-server-id',
    'expect-datadir',
];

foreach ($required as $option) {
    if (!isset($options[$option]) || $options[$option] === '') {
        stopMigration("Missing required option --{$option}");
    }
}

try {
    
    $dbHost = (string) ($options['db-host'] ?? '');
    $dbPort = (string) ($options['db-port'] ?? '');
    $dbName = (string) ($options['db-name'] ?? '');
    $dbUser = (string) ($options['db-user'] ?? '');

    if (
        $dbHost === '' ||
        $dbPort === '' ||
        $dbName === '' ||
        $dbUser === ''
    ) {
        stopMigration(
            'Provide --db-host, --db-port, --db-name, and --db-user explicitly.'
        );
    }

    if (!ctype_digit($dbPort) || (int) $dbPort < 1 || (int) $dbPort > 65535) {
        stopMigration('Invalid --db-port.');
    }

    if (!preg_match('/\A[a-zA-Z0-9_]+\z/', $dbName)) {
        stopMigration('Invalid --db-name.');
    }

    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        (string) Env::get('DB_PASSWORD', ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );


    $target = $pdo->query(
        'SELECT DATABASE() AS db,
                @@port AS port,
                @@hostname AS server,
                @@server_id AS server_id,
                @@datadir AS datadir'
    )->fetch();

           if ((string) $target['db'] !== (string) $options['expect-database']) {
        stopMigration('Connected database does not match expected database.');
    }

    if ((string) $target['port'] !== (string) $options['expect-port']) {
        stopMigration('Connected server port does not match expected port.');
    }

    if (strcasecmp((string) $target['server'], (string) $options['expect-server']) !== 0) {
        stopMigration('Connected server hostname does not match expected hostname.');
    }

    if ((string) $target['server_id'] !== (string) $options['expect-server-id']) {
        stopMigration('Connected server ID does not match expected server ID.');
    }

    $normalizePath = static fn (string $path): string =>
        strtolower(rtrim(str_replace('/', '\\', $path), '\\')) . '\\';

    if (
        $normalizePath((string) $target['datadir']) !==
        $normalizePath((string) $options['expect-datadir'])
    ) {
        stopMigration('Connected data directory does not match expected directory.');
    }

    $engineStatement = $pdo->prepare(
        "SELECT ENGINE
         FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'orders'"
    );
    $engineStatement->execute();
    $engine = $engineStatement->fetchColumn();

    if (strtolower((string) $engine) !== 'innodb') {
        stopMigration('orders must use InnoDB.');
    }

    $columnsStatement = $pdo->prepare(
        'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?'
    );
    $columnsStatement->execute(['orders']);

    $columns = [];
    foreach ($columnsStatement->fetchAll() as $column) {
        $columns[$column['COLUMN_NAME']] = $column;
    }

    $indexStatement = $pdo->prepare(
        'SELECT INDEX_NAME, NON_UNIQUE, COLUMN_NAME, SEQ_IN_INDEX
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?'
    );
    $indexStatement->execute(['orders']);

    $indexes = [];
    foreach ($indexStatement->fetchAll() as $index) {
        $indexes[$index['INDEX_NAME']][] = $index;
    }

    $requiredColumns = [
        'payment_status' => [
            'definition' =>
                "ADD COLUMN payment_status ENUM('not_required','pending','paid','failed','expired') NOT NULL DEFAULT 'not_required'",
            'type' => "enum('not_required','pending','paid','failed','expired')",
            'nullable' => 'NO',
            'default' => 'not_required',
        ],
        'paymongo_checkout_session_id' => [
            'definition' =>
                'ADD COLUMN paymongo_checkout_session_id VARCHAR(100) NULL',
            'type' => 'varchar(100)',
            'nullable' => 'YES',
            'default' => null,
        ],
        'paymongo_payment_intent_id' => [
            'definition' =>
                'ADD COLUMN paymongo_payment_intent_id VARCHAR(100) NULL',
            'type' => 'varchar(100)',
            'nullable' => 'YES',
            'default' => null,
        ],
        'paid_at' => [
            'definition' => 'ADD COLUMN paid_at DATETIME NULL',
            'type' => 'datetime',
            'nullable' => 'YES',
            'default' => null,
        ],
    ];

    $pending = [];

    foreach ($requiredColumns as $name => $spec) {
        if (!isset($columns[$name])) {
            $pending[] = $spec['definition'];
            continue;
        }

        $actual = $columns[$name];

        if (
            strtolower((string) $actual['COLUMN_TYPE']) !== $spec['type'] ||
            $actual['IS_NULLABLE'] !== $spec['nullable'] ||
            $actual['COLUMN_DEFAULT'] !== $spec['default']
        ) {
            stopMigration("Existing column {$name} has an unexpected definition.");
        }
    }

    if (!isset($columns['payment_method'])) {
        stopMigration('orders.payment_method is missing.');
    }

   $method = $columns['payment_method'];
   
   $methodType = strtolower((string) $method['COLUMN_TYPE']);
   if (
    $methodType !== "enum('cod','manual')" ||
    $method['IS_NULLABLE'] !== 'NO' ||
    trim((string) $method['COLUMN_DEFAULT'], "'") !== 'cod'
    ) {
    stopMigration('Unexpected payment_method definition; manual review required.');
    }
    
    $pending[] =
        "MODIFY COLUMN payment_method ENUM('cod','manual','paymongo') NOT NULL DEFAULT 'cod'";

        
    // Check for duplicate non-null PayMongo IDs before adding unique indexes.
    $paymongoIdColumns = [
        'paymongo_checkout_session_id',
        'paymongo_payment_intent_id',
    ];

    foreach ($paymongoIdColumns as $columnName) {
        if (!isset($columns[$columnName])) {
            // A new column has no existing values to validate.
            continue;
        }

        $duplicateStatement = $pdo->query(
            "SELECT COUNT(*)
             FROM (
                 SELECT `{$columnName}`
                 FROM orders
                 WHERE `{$columnName}` IS NOT NULL
                 GROUP BY `{$columnName}`
                 HAVING COUNT(*) > 1
             ) AS duplicates"
        );

        if ((int) $duplicateStatement->fetchColumn() > 0) {
            stopMigration(
                "Duplicate values found in orders.{$columnName}; manual review required."
            );
        }
    }


    $expectedIndexes = [
        'uq_orders_paymongo_session' => 'paymongo_checkout_session_id',
        'uq_orders_paymongo_intent' => 'paymongo_payment_intent_id',
    ];

    foreach ($expectedIndexes as $indexName => $columnName) {
        if (!isset($indexes[$indexName])) {
            $pending[] = "ADD UNIQUE INDEX {$indexName} ({$columnName})";
            continue;
        }

        $indexRows = $indexes[$indexName];
        if (
            count($indexRows) !== 1 ||
            (int) $indexRows[0]['NON_UNIQUE'] !== 0 ||
            $indexRows[0]['COLUMN_NAME'] !== $columnName ||
            (int) $indexRows[0]['SEQ_IN_INDEX'] !== 1
        ) {
            stopMigration("Existing index {$indexName} has an unexpected definition.");
        }
    }

    $report = [
        'mode' => isset($options['apply']) ? 'apply' : 'dry-run',
        'target' => [
            'database' => $target['db'],
            'port' => (string) $target['port'],
            'server' => $target['server'],
            'server_id' => (string) $target['server_id'],
            'datadir' => $target['datadir'],
        ],
        'pending_changes' => $pending,
        'applied_count' => 0,
        'note' => 'This script intentionally does not apply DDL yet.',
    ];

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

    if (isset($options['apply'])) {
        stopMigration(
            'Apply mode is intentionally disabled until the migration is reviewed and approved.'
        );
    }
} catch (Throwable $exception) {
    stopMigration('Unexpected error (' . get_class($exception) . '). Check local logs.');
}
