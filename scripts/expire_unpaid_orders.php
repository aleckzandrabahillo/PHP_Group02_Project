<?php
declare(strict_types=1);

/**
 * Close online (PayMongo) orders that were never paid.
 *
 * PayMongo checkout sessions do not expire on their own, and an unpaid order keeps its stock
 * reserved. For each order older than --minutes this script asks PayMongo whether it was paid
 * after all (and records it if so); otherwise it expires the session, cancels the order and
 * returns the stock.
 *
 *   php scripts/expire_unpaid_orders.php --minutes=60
 *
 * Schedule it every 10-15 minutes (Windows Task Scheduler or cron). Safe to run repeatedly.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/bootstrap.php';

use App\Services\PayMongoService;
use App\Services\PaymentService;

if (!PayMongoService::isConfigured()) {
    fwrite(STDERR, "PayMongo is not enabled (set PAYMONGO_ENABLED=true and a valid PAYMONGO_SECRET_KEY).\n");
    exit(1);
}

$options = getopt('', ['minutes::']);
$minutes = isset($options['minutes']) && ctype_digit((string) $options['minutes']) ? (int) $options['minutes'] : 60;

echo json_encode(
    ['minutes' => max(5, $minutes)] + (new PaymentService())->sweepStale($minutes),
    JSON_PRETTY_PRINT
) . PHP_EOL;
