<?php
namespace App\Core;

/**
 * Router — maps "METHOD + path" to "Controller@action".
 *
 * Deliberately a plain lookup table rather than regex pattern matching:
 * this site has a fixed, small set of pages, and a table is far easier
 * to read and to hand over to whoever maintains the site next.
 */
class Router
{
    private array $routes = [];

    /** Register a GET route. */
    public function get(string $path, string $handler): void
    {
        $this->routes['GET'][$path] = $handler;
    }

    /** Register a POST route. */
    public function post(string $path, string $handler): void
    {
        $this->routes['POST'][$path] = $handler;
    }

    /**
     * Work out the requested path, independent of which folder the site
     * is installed in (works at domain root or in /tianyutang/).
     */
    private function currentPath(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = BASE_URL;                       // e.g. '' or '/tianyutang'

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        $uri = '/' . trim($uri, '/');
        return $uri === '/' ? '/' : rtrim($uri, '/');
    }

    /** Find the matching route and run it. */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = $this->currentPath();

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            require BASE_PATH . '/app/Views/errors/404.php';
            return;
        }

        [$controllerName, $action] = explode('@', $handler);
        $class = 'App\\Controllers\\' . $controllerName;

        if (!class_exists($class) || !method_exists($class, $action)) {
            http_response_code(500);
            die(DEBUG_MODE ? "Route handler not found: {$handler}" : '系統錯誤。');
        }

        (new $class())->$action();
    }
}
