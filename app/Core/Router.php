<?php

namespace App\Core;

use App\Middleware\MiddlewareInterface;
use Closure;

class Router
{
    private array $routes = [];
    private array $groupStack = [];
    private array $globalMiddleware = [];

    public function use(string|MiddlewareInterface $middleware): self
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    public function group(array $attributes, callable $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    public function get(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function options(string $path, mixed $handler, array $middleware = []): self
    {
        return $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, mixed $handler, array $middleware = []): self
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            if (!empty($group['middleware'])) {
                $m = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $groupMiddleware = array_merge($groupMiddleware, $m);
            }
        }

        $fullPath = rtrim($prefix . '/' . ltrim($path, '/'), '/');
        if (empty($fullPath)) {
            $fullPath = '/';
        }

        // Convert {param:\d+} or {param} into regex
        $pattern = preg_replace_callback('/\{([a-zA-Z0-9_]+)(?::([^}]+))?\}/', function ($matches) {
            $name = $matches[1];
            $regex = $matches[2] ?? '[^/]+';
            return "(?P<{$name}>{$regex})";
        }, $fullPath);

        $pattern = "#^" . $pattern . "$#";

        $allMiddleware = array_merge($groupMiddleware, $middleware);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $allMiddleware,
        ];

        return $this;
    }

    public function dispatch(?Request $request = null): void
    {
        $req = $request ?? Request::createFromGlobals();
        $method = $req->getMethod();
        $path = rtrim($req->getPath(), '/');
        if (empty($path)) {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $req->setRouteParams($params);

                $pipeline = array_merge($this->globalMiddleware, $route['middleware']);
                $handler = $this->resolveHandler($route['handler'], $req, $params);

                $runner = array_reduce(
                    array_reverse($pipeline),
                    function ($next, $middleware) {
                        return function (Request $request) use ($next, $middleware) {
                            $instance = is_string($middleware) ? new $middleware() : $middleware;
                            return $instance->handle($request, $next);
                        };
                    },
                    $handler
                );

                $runner($req);
                return;
            }
        }

        Response::error('Endpoint not found', Response::HTTP_NOT_FOUND, [
            'path' => $path,
            'method' => $method,
        ]);
    }

    private function resolveHandler(mixed $handler, Request $request, array $params): Closure
    {
        return function (Request $req) use ($handler, $params) {
            if ($handler instanceof Closure) {
                return $handler($req, $params);
            }

            if (is_array($handler) && count($handler) === 2) {
                [$class, $method] = $handler;
                $instance = is_string($class) ? new $class() : $class;
                return $instance->$method($req, $params);
            }

            if (is_string($handler) && str_contains($handler, '@')) {
                [$class, $method] = explode('@', $handler, 2);
                $instance = new $class();
                return $instance->$method($req, $params);
            }

            throw new \RuntimeException("Invalid route handler provided");
        };
    }
}

