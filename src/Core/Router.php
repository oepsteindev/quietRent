<?php

namespace QuietRent\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri    = rtrim($uri, '/') ?: '/';

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            $pattern = $this->toRegex($routePath);

            if ($routeMethod !== $method && !($method === 'POST' && isset($_POST['_method']) && $_POST['_method'] === $routeMethod)) {
                continue;
            }
            if ($method === 'POST' && isset($_POST['_method'])) {
                if ($_POST['_method'] !== $routeMethod && $routeMethod !== 'POST') {
                    continue;
                }
            }

            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->call($handler, $params);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function toRegex(string $path): string
    {
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function call(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $instance = new $class();
            $instance->$method($params);
        } else {
            $handler($params);
        }
    }
}
