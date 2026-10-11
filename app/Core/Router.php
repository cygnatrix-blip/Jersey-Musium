<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Small regex router with route groups and a middleware pipeline.
 *   $r->get('/p/{slug}', [ProductController::class, 'show']);
 *   $r->group('/admin', ['admin'], fn ($r) => ...);
 * Middleware strings may carry a parameter: 'can:orders.manage', 'throttle:5,60'.
 */
final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function __construct(private array $middlewareMap, private array $globalMiddleware = [])
    {
    }

    public function get(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add(['GET', 'HEAD'], $path, $handler, $middleware);
    }

    public function post(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add(['POST'], $path, $handler, $middleware);
    }

    public function delete(string $path, array|Closure $handler, array $middleware = []): void
    {
        $this->add(['DELETE'], $path, $handler, $middleware);
    }

    public function group(string $prefix, array $middleware, callable $routes): void
    {
        [$prevPrefix, $prevMw] = [$this->prefix, $this->groupMiddleware];
        $this->prefix .= $prefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $routes($this);
        [$this->prefix, $this->groupMiddleware] = [$prevPrefix, $prevMw];
    }

    private function add(array $methods, string $path, array|Closure $handler, array $middleware): void
    {
        $full = '/' . trim($this->prefix . $path, '/');
        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            fn ($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $full
        );
        $this->routes[] = [
            'methods'    => $methods,
            'regex'      => '#^' . $regex . '$#',
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $m)) {
                continue;
            }
            if (!in_array($request->method(), $route['methods'], true)) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            $core = function (Request $req) use ($route, $params): Response {
                $h = $route['handler'];
                $result = $h instanceof Closure
                    ? $h($req, ...$params)
                    : (new $h[0]())->{$h[1]}($req, ...array_values($params));
                return $result instanceof Response ? $result : new Response((string) $result);
            };
            return $this->pipeline(array_merge($this->globalMiddleware, $route['middleware']), $core)($request);
        }
        // Unmatched requests still pass through global middleware (security headers, error page).
        return $this->pipeline($this->globalMiddleware, fn (Request $req) => throw new HttpException($allowed ? 405 : 404))($request);
    }

    private function pipeline(array $middleware, Closure $core): Closure
    {
        $next = $core;
        foreach (array_reverse($middleware) as $name) {
            [$key, $param] = array_pad(explode(':', $name, 2), 2, null);
            $class = $this->middlewareMap[$key] ?? throw new \LogicException("Unknown middleware [$key]");
            $next = fn (Request $req) => (new $class())->handle($req, $next, $param);
        }
        return $next;
    }
}
