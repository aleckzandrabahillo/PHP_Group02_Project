<?php

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class PayMongoService
{
    private const API_URL = 'https://api.paymongo.com/v1/checkout_sessions';

    /**
     * Create a hosted checkout session.
     *
     * Each line item must contain:
     * name, amount (integer centavos), quantity, currency (PHP).
     *
     * @return array{id: string, checkout_url: string}
     */
    public function createCheckoutSession(
        array $lineItems,
        string $successUrl,
        string $cancelUrl,
        string $referenceNumber,
        string $description = 'AVELA order'
    ): array {
        $secretKey = Env::get('PAYMONGO_SECRET_KEY');

        if (
            !is_string($secretKey)
            || !preg_match('/^sk_(test|live)_\S+$/D', $secretKey)
        ) {
            throw new RuntimeException(
                'PayMongo is not configured. Please try again later.'
            );
        }

        if (
            $secretKey === 'sk_test_your_key_here'
            || $secretKey === 'sk_live_your_key_here'
        ) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        if (count($lineItems) < 1 || count($lineItems) > 100) {
            throw new RuntimeException('Invalid checkout items.');
        }

        $validatedItems = [];

        foreach ($lineItems as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('Invalid checkout item.');
            }

            $name = $item['name'] ?? null;
            $amount = $item['amount'] ?? null;
            $quantity = $item['quantity'] ?? null;
            $currency = $item['currency'] ?? null;

            if (
                !is_string($name)
                || trim($name) === ''
                || mb_strlen($name, 'UTF-8') > 255
                || !is_int($amount)
                || $amount < 1
                || !is_int($quantity)
                || $quantity < 1
                || $quantity > 99
                || $currency !== 'PHP'
            ) {
                throw new RuntimeException('Invalid checkout item details.');
            }

            $validatedItems[] = [
                'name' => trim($name),
                'amount' => $amount,
                'quantity' => $quantity,
                'currency' => 'PHP',
            ];
        }

        foreach ([$successUrl, $cancelUrl] as $url) {
            if (!$this->isAllowedReturnUrl($url)) {
                throw new RuntimeException(
                    'Invalid checkout return URL configuration.'
                );
            }
        }

        if (
            !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $referenceNumber)
        ) {
            throw new RuntimeException('Invalid order reference.');
        }

        $payload = [
            'data' => [
                'attributes' => [
                    'line_items' => $validatedItems,
                    'payment_method_types' => [
                        'gcash',
                        'paymaya',
                        'card',
                    ],
                    'success_url' => $successUrl,
                    'cancel_url' => $cancelUrl,
                    'reference_number' => $referenceNumber,
                    'description' => $description,
                    'show_line_items' => true,
                    'send_email_receipt' => false,
                ],
            ],
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException(
                'Could not prepare the checkout request.',
                0,
                $e
            );
        }

        $curl = curl_init(self::API_URL);

        if ($curl === false) {
            throw new RuntimeException(
                'Could not initialize the checkout request.'
            );
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Basic '
                        . base64_encode($secretKey . ':'),
                ],
                CURLOPT_POSTFIELDS => $json,
            ]);

            $response = curl_exec($curl);
            $httpStatus = (int) curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

            if ($response === false) {
                throw new RuntimeException(
                    'Could not contact PayMongo. Please try again later.'
                );
            }

            if ($httpStatus < 200 || $httpStatus >= 300) {
                // Do not expose raw API responses to customers.
                throw new RuntimeException(
                    'PayMongo could not create the checkout session '
                    . '(HTTP ' . $httpStatus . ').'
                );
            }

            try {
                $decoded = json_decode(
                    $response,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (\JsonException $e) {
                throw new RuntimeException(
                    'PayMongo returned an invalid response.',
                    0,
                    $e
                );
            }

            $sessionId = $decoded['data']['id'] ?? null;
            $checkoutUrl =
                $decoded['data']['attributes']['checkout_url'] ?? null;

            if (
                !is_string($sessionId)
                || !preg_match('/^cs_[A-Za-z0-9_-]+$/D', $sessionId)
                || !is_string($checkoutUrl)
                || !$this->isHttpsUrl($checkoutUrl)
            ) {
                throw new RuntimeException(
                    'PayMongo returned an unexpected checkout response.'
                );
            }

            return [
                'id' => $sessionId,
                'checkout_url' => $checkoutUrl,
            ];
        } finally {
            curl_close($curl);
        }
    }

    /**
     * Online payment is offered only when it is switched on (PAYMONGO_ENABLED=true,
     * set after the database migration) and a real secret key is present.
     */
    public static function isConfigured(): bool
    {
        $key = Env::get('PAYMONGO_SECRET_KEY');

        return Env::get('PAYMONGO_ENABLED', false) === true
            && is_string($key)
            && preg_match('/^sk_(test|live)_\S+$/D', $key) === 1
            && $key !== 'sk_test_your_key_here'
            && $key !== 'sk_live_your_key_here';
    }

    /**
     * Fetch a checkout session from PayMongo (the authoritative payment state).
     *
     * @return array<string, mixed> ['id' => 'cs_...', 'attributes' => [...]]
     */
    public function retrieveCheckoutSession(string $sessionId): array
    {
        $decoded = $this->request('GET', $sessionId);
        $data = $decoded['data'] ?? null;

        if (!is_array($data) || ($data['id'] ?? null) !== $sessionId) {
            throw new RuntimeException(
                'PayMongo returned an unexpected checkout response.'
            );
        }

        return $data;
    }

    /** Close a session so it can no longer accept payment. */
    public function expireCheckoutSession(string $sessionId): void
    {
        $this->request('POST', $sessionId, '/expire');
    }

    /**
     * Verify the Paymongo-Signature header of a webhook request.
     *
     * Header format: t=<unix time>,te=<test signature>,li=<live signature>.
     * Signature = HMAC-SHA256("<t>.<raw body>", webhook secret). The test key
     * pairs with "te" and the live key with "li".
     */
    public function verifyWebhookSignature(string $rawBody, string $header): bool
    {
        $secret = Env::get('PAYMONGO_WEBHOOK_SECRET');
        $key = Env::get('PAYMONGO_SECRET_KEY');

        if (
            !is_string($secret)
            || preg_match('/^whsk_\S+$/D', $secret) !== 1
            || !is_string($key)
        ) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $pair = explode('=', trim($piece), 2);
            if (count($pair) === 2) {
                $parts[trim($pair[0])] = trim($pair[1]);
            }
        }

        $timestamp = $parts['t'] ?? '';
        $provided = $parts[str_starts_with($key, 'sk_live_') ? 'li' : 'te'] ?? '';

        if (!ctype_digit($timestamp) || $provided === '') {
            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret),
            $provided
        );
    }

    /**
     * @param string $sessionId cs_... id
     * @param string $suffix    optional path suffix such as "/expire"
     * @return array<string, mixed>
     * @throws RuntimeException code = HTTP status, or 0 for network failures
     */
    private function request(string $method, string $sessionId, string $suffix = ''): array
    {
        $secretKey = Env::get('PAYMONGO_SECRET_KEY');

        if (
            !is_string($secretKey)
            || preg_match('/^sk_(test|live)_\S+$/D', $secretKey) !== 1
        ) {
            throw new RuntimeException('PayMongo is not configured.');
        }

        if (preg_match('/^cs_[A-Za-z0-9_-]+$/D', $sessionId) !== 1) {
            throw new RuntimeException('Invalid checkout session id.');
        }

        $curl = curl_init(self::API_URL . '/' . $sessionId . $suffix);

        if ($curl === false) {
            throw new RuntimeException('Could not initialize the PayMongo request.');
        }

        try {
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode($secretKey . ':'),
                ],
            ]);

            if ($method === 'POST') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, '');
            }

            $response = curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

            if ($response === false) {
                throw new RuntimeException('Could not contact PayMongo.', 0);
            }

            if ($httpStatus < 200 || $httpStatus >= 300) {
                throw new RuntimeException(
                    'PayMongo request failed (HTTP ' . $httpStatus . ').',
                    $httpStatus
                );
            }

            $decoded = json_decode((string) $response, true);

            if (!is_array($decoded)) {
                throw new RuntimeException('PayMongo returned an invalid response.');
            }

            return $decoded;
        } finally {
            curl_close($curl);
        }
    }

    private function isAllowedReturnUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        if ($scheme === 'https') {
            return true;
        }

        $isLocalHost = in_array(
            $host,
            ['localhost', '127.0.0.1', '::1'],
            true
        );

        return $scheme === 'http'
            && $isLocalHost
            && Env::get('APP_ENV', 'development') === 'development';
    }

    private function isHttpsUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && isset($parts['host'])
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }
}