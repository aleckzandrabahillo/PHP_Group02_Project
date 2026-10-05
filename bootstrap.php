<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/app/Core/Env.php';
Env::load(__DIR__ . '/.env');


spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/app/Support/helpers.php';

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));

ini_set('display_errors', '0');
error_reporting(E_ALL);

$logDir = __DIR__ . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}

set_exception_handler(function (Throwable $e) use ($logDir): void {
    $line = sprintf("[%s] %s in %s:%d\n%s\n\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents($logDir . '/app.log', $line, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    $pageTitle = 'Something went wrong';
    $message = env('APP_ENV', 'production') === 'development'
        ? 'Avela hit an unexpected error. The technical details were written to storage/logs/app.log.'
        : 'Avela could not complete that request. Please try again.';
    require __DIR__ . '/app/Views/errors/500.php';
});

Session::start();
