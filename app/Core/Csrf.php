<?php

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        $token = Session::get('_csrf');
        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            Session::put('_csrf', $token);
        }
        return $token;
    }

    public static function validate(?string $token): bool
    {
        $stored = Session::get('_csrf');
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    public static function enforce(?string $token): void
    {
        if (!self::validate($token)) {
            http_response_code(419);
            exit('Your form session expired. Go back, refresh the page, and try again.');
        }
    }
}
