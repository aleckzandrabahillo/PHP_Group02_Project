<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name((string) env('SESSION_NAME', 'avela_session'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) env('SESSION_SECURE_COOKIE', false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        session_start();

        if (isset($_SESSION['auth_user'])) {
            $timeout = (int) env('SESSION_TIMEOUT_MINUTES', 30) * 60;
            $last = (int) ($_SESSION['last_activity'] ?? time());
            if ($timeout > 0 && (time() - $last) > $timeout) {
                self::forgetAuth();
                session_regenerate_id(true);
                self::put('_auth_expired_notice', true);
                self::flash('warning', 'Your session expired because it was inactive. Please sign in again.');
            } else {
                $_SESSION['last_activity'] = time();
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function flashes(): array
    {
        $items = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $items;
    }

    public static function keepOld(array $input): void
    {
        unset($input['password'], $input['password_confirmation'], $input['_token'], $input['captcha']);
        $_SESSION['_old'] = $input;
    }

    public static function old(string $key, string $default = ''): string
    {
        return (string) ($_SESSION['_old'][$key] ?? $default);
    }

    public static function clearOld(): void
    {
        unset($_SESSION['_old']);
    }

    public static function forgetAuth(): void
    {
        unset($_SESSION['auth_user'], $_SESSION['last_activity']);
    }
}
