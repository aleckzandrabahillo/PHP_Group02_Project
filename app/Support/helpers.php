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
    $path=ltrim($path,'/');$url=url('/assets/'.$path);
    // Updated CSS/JS must not leave an older interaction cached in an open browser.
    if(preg_match('~^[A-Za-z0-9_/-]+\.(?:css|js)$~D',$path)){
        $file=dirname(__DIR__,2).'/public/assets/'.$path;
        if(is_file($file))$url.='?v='.substr(hash_file('sha256',$file),0,12);
    }
    return $url;
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

/** A small shared outline vocabulary; icon names never become markup. */
function staff_icon(string $name): string
{
    $paths = [
        'overview'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'customers'=>'<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-4-5.7"/>',
        'catalog'=>'<path d="m12 3 9 5-9 5-9-5 9-5ZM3 8v9l9 5 9-5V8M12 13v9M7.5 5.5l9 5"/>',
        'inventory'=>'<path d="M3 9h18v12H3zM2 3h20v6H2zM9 13h6"/>',
        'orders'=>'<path d="M7 7V6a5 5 0 0 1 10 0v1M4 7h16l1 15H3L4 7Z"/>',
        'security'=>'<path d="m12 2 9 4v6c0 5-9 10-9 10S3 17 3 12V6l9-4Z"/><path d="m8 12 3 3 5-6"/>',
        'activity'=>'<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4 22v-2a8 8 0 0 1 16 0v2"/>',
        'store'=>'<path d="M4 10v11h16V10M3 3h18l1 7H2l1-7ZM9 21v-7h6v7"/>',
        'logout'=>'<path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4"/>',
        'plus'=>'<path d="M12 5v14M5 12h14"/>',
        'menu'=>'<path d="M4 6h16M4 12h16M4 18h16"/>',
        'arrow'=>'<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'tag'=>'<path d="M3 3h8l10 10-8 8L3 11V3Z"/><circle cx="7.5" cy="7.5" r=".7"/>',
    ];
    return '<svg class="staff-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['overview']).'</svg>';
}

function staff_status(string $value): string
{
    $tone=match(strtolower($value)) {
        'active','in stock','delivered','paid','success'=>'good',
        'pending','processing','low stock','review'=>'notice',
        'locked','out of stock','failure','failed'=>'attention',
        default=>'neutral',
    };
    return '<span class="staff-status staff-status-'.$tone.'">'.e(ucfirst(str_replace('_',' ',$value))).'</span>';
}

/** Bounded, escaped destinations retain the caller's existing validated filters. */
function pagination_nav(int $page, int $pages, callable $urlFor, string $label='Pages', int $window=2): string
{
    $pages=max(1,$pages);$page=max(1,min($page,$pages));$window=max(1,min(4,$window));
    if($pages===1)return '';
    $shown=[1,$pages];for($offset=-$window;$offset<=$window;$offset++)if($page+$offset>=1&&$page+$offset<=$pages)$shown[]=$page+$offset;
    $shown=array_unique($shown);sort($shown,SORT_NUMERIC);
    $html='<nav class="staff-pagination order-pagination" aria-label="'.e($label).'">';
    if($page>1)$html.='<a rel="prev" href="'.e($urlFor($page-1)).'">Previous</a>';
    $last=0;foreach($shown as $number){if($last&&$number-$last>1)$html.='<span class="page-gap" aria-hidden="true">…</span>';
        $html.=$number===$page?'<span class="page-current" aria-current="page">'.$number.'</span>':'<a aria-label="Page '.$number.'" href="'.e($urlFor($number)).'">'.$number.'</a>';$last=$number;}
    if($page<$pages)$html.='<a rel="next" href="'.e($urlFor($page+1)).'">Next</a>';
    return $html.'<span class="page-summary">Page '.$page.' of '.$pages.'</span></nav>';
}
