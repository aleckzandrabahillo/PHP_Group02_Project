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

/**
 * Numbered pagination: Previous, 1 … 4 5 [6] 7 8 … 20, Next.
 * $urlFor receives a page number and returns the (unescaped) URL for it.
 */
function pagination_nav(int $page, int $pages, callable $urlFor, string $label = 'Pages', int $window = 2): string
{
    $pages = max(1, $pages);
    $page = max(1, min($page, $pages));
    if ($pages === 1) return '';
    $numbers = [1, $pages];
    for ($i = $page - $window; $i <= $page + $window; $i++) {
        if ($i >= 1 && $i <= $pages) $numbers[] = $i;
    }
    $numbers = array_values(array_unique($numbers));
    sort($numbers);
    $html = '<nav class="order-pagination page-numbers" aria-label="' . e($label) . '">';
    if ($page > 1) $html .= '<a href="' . e($urlFor($page - 1)) . '" rel="prev">Previous</a>';
    $last = 0;
    foreach ($numbers as $n) {
        if ($last && $n - $last > 1) $html .= '<span class="page-gap" aria-hidden="true">…</span>';
        $html .= $n === $page
            ? '<span class="page-current" aria-current="page">' . $n . '</span>'
            : '<a href="' . e($urlFor($n)) . '" aria-label="Page ' . $n . '">' . $n . '</a>';
        $last = $n;
    }
    if ($page < $pages) $html .= '<a href="' . e($urlFor($page + 1)) . '" rel="next">Next</a>';
    return $html . '<span class="page-summary">Page ' . $page . ' of ' . $pages . '</span></nav>';
}
