<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
ini_set('log_errors', '0');
$root = dirname(__DIR__);
require_once $root . '/app/Core/Env.php';
\App\Core\Env::load($root . '/.env');
require_once $root . '/app/Support/helpers.php';
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require_once $file;
    }
});

function avela_import_main(array $arguments): int
{
    try {
        $options = [];
        foreach (array_slice($arguments, 1) as $argument) {
            if (!preg_match('/^--(apply|dry-run|help|manifest|actor|expect-host|expect-port|expect-database)(?:=(.*))?$/D', $argument, $match)) {
                throw new DomainException('Unknown option. Use --help for supported options.');
            }
            $name = $match[1];
            if (array_key_exists($name, $options)) throw new DomainException('Duplicate option: --' . $name);
            if (in_array($name, ['apply','dry-run','help'], true)) {
                if (isset($match[2])) throw new DomainException('Flags do not accept values.');
                $options[$name] = true;
            } else {
                if (!isset($match[2]) || $match[2] === '') throw new DomainException('Option requires a value: --' . $name);
                $options[$name] = $match[2];
            }
        }
        if (isset($options['help'])) {
            echo "Avela demonstration catalog importer (CLI only; dry-run by default).\n";
            echo "php scripts/import_demo_catalog.php [--dry-run] [--manifest=path]\n";
            echo "php scripts/import_demo_catalog.php --apply --actor=STAFF_USERNAME --expect-host=HOST --expect-port=PORT --expect-database=DATABASE\n";
            echo "Preserves six seed products and adds fourteen; conflicting or extra records stop the operation.\n";
            return 0;
        }
        if (isset($options['apply'], $options['dry-run'])) throw new DomainException('Choose either --apply or --dry-run.');
        $confirmation = [];
        if (isset($options['expect-port'])) {
            if (!ctype_digit($options['expect-port']) || (int) $options['expect-port'] < 1 || (int) $options['expect-port'] > 65535) {
                throw new DomainException('Expected port must be between 1 and 65535.');
            }
            $confirmation['port'] = (int) $options['expect-port'];
        }
        foreach (['host','database'] as $name) if (isset($options['expect-' . $name])) $confirmation[$name] = $options['expect-' . $name];
        $manifest = \App\Services\DemoCatalogImporter::readManifest($options['manifest'] ?? dirname(__DIR__) . '/database/demo_catalog.json');
        $result = (new \App\Services\DemoCatalogImporter(\App\Core\Database::connection()))->run(
            $manifest, isset($options['apply']), $options['actor'] ?? null, $confirmation
        );
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
        return 0;
    } catch (DomainException $e) {
        fwrite(STDERR, 'IMPORT STOPPED: ' . $e->getMessage() . PHP_EOL);
    } catch (Throwable $e) {
        fwrite(STDERR, "IMPORT FAILED: Check database access and constraints, then run dry-run before retrying. Private diagnostics are suppressed.\n");
    }
    return 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) exit(avela_import_main($argv));
