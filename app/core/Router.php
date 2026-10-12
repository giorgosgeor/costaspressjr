<?php

class Router
{
    private array $routes = [];
    /** Routes declared with a trailing /{id}, by method: [method][path] => true */
    private array $takesId = [];
    /** @var callable|null */
    private $notFound = null;

    /** What to render when nothing matches; the 404 status is already set. */
    public function setNotFound(callable $action): void
    {
        $this->notFound = $action;
    }

    /**
     * Register a GET route. Ending the path in /{id} lets it take one more
     * path segment, handed to the action: '/product/{id}' answers /product
     * and /product/12-t-shirt (→ product('12-t-shirt')). Without it, a route
     * answers its own path only, so /about/anything is a 404, not a second
     * copy of the About page.
     */
    public function get(string $uri, callable $action): void
    {
        $this->add('GET', $uri, $action);
    }

    /** Register a POST route; /{id} works as for get(). */
    public function post(string $uri, callable $action): void
    {
        $this->add('POST', $uri, $action);
    }

    private function add(string $method, string $uri, callable $action): void
    {
        if (str_ends_with($uri, '/{id}')) {
            $uri = substr($uri, 0, -strlen('/{id}'));
            $this->takesId[$method][$uri] = true;
        }
        $this->routes[$method][$uri] = $action;
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH);

        // Exact match first
        if (isset($this->routes[$method][$path])) {
            call_user_func($this->routes[$method][$path]);
            return;
        }

        // Then the routes that take one more segment (e.g. /admin/products/edit/5)
        $slash = strrpos($path, '/');
        $base  = $slash > 0 ? substr($path, 0, $slash) : null;
        if ($base !== null && isset($this->takesId[$method][$base])
            && preg_match('#^[\w-]+$#', substr($path, $slash + 1))) {
            call_user_func($this->routes[$method][$base], substr($path, $slash + 1));
            return;
        }

        http_response_code(404);
        if ($this->notFound) {
            call_user_func($this->notFound);
            return;
        }
        echo '404 Not Found';
    }
}
