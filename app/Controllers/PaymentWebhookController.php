<?php

namespace App\Controllers;

use App\Services\PaymentService;

/**
 * Receives PayMongo webhooks. No session, login or CSRF token applies here: the request is
 * authenticated by its Paymongo-Signature header (see PayMongoService::verifyWebhookSignature).
 */
final class PaymentWebhookController
{
    private const MAX_BODY_BYTES = 1048576;

    public function __construct(private readonly PaymentService $payments = new PaymentService()) {}

    public function paymongo(): void
    {
        header('Cache-Control: no-store');
        header('Content-Type: text/plain; charset=utf-8');

        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY_BYTES + 1);
        if (strlen($raw) > self::MAX_BODY_BYTES) {
            http_response_code(413);
            echo 'payload too large';
            return;
        }

        $signature = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

        try {
            [$status, $message] = $this->payments->handleWebhook($raw, is_string($signature) ? $signature : '');
        } catch (\Throwable $e) {
            // A non-2xx answer makes PayMongo retry later, which is what we want for a transient fault.
            $dir = dirname(__DIR__, 2) . '/storage/logs';
            if (is_dir($dir)) {
                @file_put_contents($dir . '/payments.log', sprintf("[%s] webhook error: %s\n", date('c'), $e->getMessage()), FILE_APPEND | LOCK_EX);
            }
            $status = 500;
            $message = 'error';
        }

        http_response_code($status);
        echo $message;
    }
}
