<?php

namespace App\Middleware;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;

final class AuthGuard
{
    public static function guest(): void
    {
        if (Session::get('auth_user')) Response::redirect(self::dashboardPath());
    }

    public static function auth(): array
    {
        $user = Session::get('auth_user');
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
