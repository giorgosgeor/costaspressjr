<?php

class Router
{
    private array $routes = [];

    public function get(string $uri, callable $action): void
    {
        $this->routes['GET'][$uri] = $action;
    }

    public function post(string $uri, callable $action): void
    {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);

        // Exact match first
        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
            return;
        }

        // Check for routes with parameters (e.g., /admin/products/edit/5)
        // Sort routes by length (longest first) to match more specific routes first
        $routes = $this->routes[$method] ?? [];
        uksort($routes, function($a, $b) {
            return strlen($b) - strlen($a);
        });

        foreach ($routes as $route => $action) {
            // Check if current path matches route with parameter (e.g., /shop/custom_product/1)
            if (preg_match('#^' . preg_quote($route, '#') . '/([\w-]+)$#', $path, $matches)) {
                $param = $matches[1];
                call_user_func($action, $param);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
