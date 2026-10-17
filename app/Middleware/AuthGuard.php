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
        $current = (new User())->findById((int)$user['id']);
        $path = parse_url(current_relative_uri(), PHP_URL_PATH);
        if ($current && \App\Services\PasswordService::expired($current) && !in_array($path,['/account/password','/logout'],true)) {
            Session::flash('warning','Your password has expired under the configured policy. Change it to continue.');
            Response::redirect('/account/password');
        }
        return $user;
    }

    private static function currentUser(): ?array
    {
        $cached = Session::get('auth_user');
        if (!is_array($cached)) return null;

        $user = (new User())->findById((int) ($cached['id'] ?? 0));
        $version = $user ? \App\Services\MfaService::sessionVersion((int) $user['id']) : null;
        // A changed role must pass its normal login/MFA path before gaining access.
        if (!User::canAuthenticate($user) || ($cached['role'] ?? '') !== $user['role']
            || (($user['password_version'] ?? '') !== '' && !hash_equals($user['password_version'], (string)Session::get('auth_password_version','')))
            || ($version !== null && (!is_string(Session::get('auth_mfa_version')) || !hash_equals($version, Session::get('auth_mfa_version'))))) {
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
