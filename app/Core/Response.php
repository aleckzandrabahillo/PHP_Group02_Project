<?php

namespace App\Core;

final class Response
{
    public static function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    /**
     * Redirect to PayMongo's hosted checkout. Only https URLs on paymongo.com are
     * followed, so a tampered API response can never send a customer elsewhere.
     */
    public static function redirectExternal(string $url): never
    {
        $parts = parse_url($url);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';

        if (
            !is_array($parts)
            || preg_match('/[\x00-\x1F\x7F\s]/', $url) === 1
            || ($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || ($host !== 'paymongo.com' && !str_ends_with($host, '.paymongo.com'))
        ) {
            throw new \RuntimeException('Blocked redirect to an untrusted payment URL.');
        }

        header('Location: ' . $url);
        exit;
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
