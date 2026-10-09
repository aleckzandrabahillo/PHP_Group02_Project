<?php

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\User;

final class AuthGuard
{
    public static function guest(): void
    {
        if (self::currentUser()) Response::redirect(self::dashboardPath());
    }

    public static function auth(): array
    {
        $user = self::currentUser();
        if (!is_array($user)) {
            if (Session::get('_auth_expired_notice')) {
                Session::forget('_auth_expired_notice');
            } else {
                Session::flash('warning', 'Please sign in to continue.');
            }
            Response::redirect('/login');
        }
        return $user;
    }

    private static function currentUser(): ?array
    {
        $cached = Session::get('auth_user');
        if (!is_array($cached)) return null;

        $user = (new User())->findById((int) ($cached['id'] ?? 0));
        // A changed role must pass its normal login/MFA path before gaining access.
        if (!User::canAuthenticate($user) || ($cached['role'] ?? '') !== $user['role']) {
            Session::forgetAuth();
            session_regenerate_id(true);
            Session::flash('warning', 'Please sign in to continue.');
            // Avoid a duplicate warning when auth() redirects this request.
            Session::put('_auth_expired_notice', true);
            return null;
        }

        $current = [
            'id' => (int) $user['id'], 'email' => $user['email'],
            'username' => $user['username'], 'role' => $user['role'],
        ];
        Session::put('auth_user', $current);
        return $current;
    }

    public static function roles(array $roles): array
    {
        $user = self::auth();
        if (!in_array($user['role'] ?? '', $roles, true)) {
            http_response_code(403);
            View::render('errors/403', ['pageTitle' => 'Access denied'], 'plain');
            exit;
        }
        return $user;
    }

    public static function dashboardPath(): string
    {
        $role = Session::get('auth_user')['role'] ?? null;
        return match ($role) {
            'admin' => '/admin',
            'catalog_manager' => '/catalog',
            'customer' => '/',
            default => '/',
        };
    }
}
