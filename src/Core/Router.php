<?php
/**
 * CARI-IPTV Simple Router
 */

namespace CariIPTV\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function group(array $options, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;

        if (isset($options['prefix'])) {
            $this->prefix = $previousPrefix . '/' . trim($options['prefix'], '/');
        }

        if (isset($options['middleware'])) {
            $this->groupMiddleware = array_merge(
                $this->groupMiddleware,
                (array) $options['middleware']
            );
        }

        $callback($this);

        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function any(string $path, callable|array $handler, array $middleware = []): void
    {
        foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH'] as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middleware): void
    {
        $fullPath = $this->prefix . '/' . trim($path, '/');
        $fullPath = '/' . trim($fullPath, '/');

        // Convert path parameters to regex
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $fullPath);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function addMiddleware(string $name, callable $middleware): void
    {
        $this->middleware[$name] = $middleware;
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        // Handle method override for forms
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extract named parameters
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                // Run middleware
                foreach ($route['middleware'] as $middlewareName) {
                    if (isset($this->middleware[$middlewareName])) {
                        $result = call_user_func($this->middleware[$middlewareName]);
                        if ($result === false) {
                            return;
                        }
                    }
                }

                // Call handler
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = new $class();
                    call_user_func_array([$controller, $method], $params);
                } else {
                    call_user_func_array($handler, $params);
                }

                return;
            }
        }

        // No route found
        http_response_code(404);
        $this->showError(404, 'Page Not Found');
    }

    private function showError(int $code, string $message): void
    {
        if (file_exists(dirname(__DIR__, 2) . '/templates/errors/' . $code . '.php')) {
            include dirname(__DIR__, 2) . '/templates/errors/' . $code . '.php';
        } else {
            echo "<h1>$code - $message</h1>";
        }
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}
