<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;
use App\Core\Csrf;

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    if ($dir === '/' || $dir === '.' || $dir === '\\') {
        return '';
    }
    return rtrim($dir, '/');
}

function url(string $path = '/'): string
{
    $path = '/' . ltrim($path, '/');
    return base_path() . ($path === '//' ? '/' : $path);
}

function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function old(string $key, string $default = ''): string
{
    return e(Session::old($key, $default));
}

function auth_user(): ?array
{
    return Session::get('auth_user');
}

function is_active_path(string $path): bool
{
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $base = base_path();
    if ($base !== '' && str_starts_with($current, $base)) {
        $current = substr($current, strlen($base)) ?: '/';
    }
    return $current === $path || ($path !== '/' && str_starts_with($current, rtrim($path, '/') . '/'));
}

function current_relative_uri(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($uri);
    $path = (string) ($parts['path'] ?? '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base)) ?: '/';
    }
    $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
    return ($path === '' ? '/' : $path) . $query;
}

function product_visual_key(string $categoryName): string
{
    $name = strtolower(trim($categoryName));
    if (str_contains($name, 'conditioner')) return 'conditioner';
    if (str_contains($name, 'treatment') || str_contains($name, 'mask')) return 'treatment';
    if (str_contains($name, 'serum')) return 'serum';
    if (str_contains($name, 'oil')) return 'oil';
    if (str_contains($name, 'tool') || str_contains($name, 'comb') || str_contains($name, 'brush')) return 'tools';
    if (str_contains($name, 'shampoo') || str_contains($name, 'cleanse')) return 'shampoo';
    return 'generic';
}
function product_image_url(?string $imagePath): ?string
{
    $imagePath = trim((string) $imagePath);
    if ($imagePath === '') return null;
    $cleanPath = ltrim(str_replace('\\', '/', $imagePath), '/');
    if (str_starts_with($cleanPath, 'uploads/') || str_starts_with($cleanPath, 'assets/')) {
        return url('/' . $cleanPath);
    }
    return url('/uploads/' . $cleanPath);
}

function product_placeholder_url(string $categoryName): string
{
    return asset('images/placeholders/' . product_visual_key($categoryName) . '.svg');
}

