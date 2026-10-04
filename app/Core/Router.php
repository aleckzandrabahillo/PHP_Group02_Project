<?php

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void { $this->map('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->map('POST', $path, $handler); }

    private function map(string $method, string $path, callable|array $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = base_path();
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base)) ?: '/';
        }
        $path = rtrim($path, '/') ?: '/';
        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            $exists = false;
            foreach ($this->routes as $routeMethod => $paths) {
                if (isset($paths[$path])) { $exists = true; break; }
            }
            if ($exists) {
                http_response_code(405);
                header('Allow: ' . implode(', ', array_keys(array_filter($this->routes, fn($paths) => isset($paths[$path])))));
                echo 'Method not allowed.';
                return;
            }
            http_response_code(404);
            View::render('errors/404', ['pageTitle' => 'Page not found'], 'plain');
            return;
        }
        call_user_func($handler);
    }
}
