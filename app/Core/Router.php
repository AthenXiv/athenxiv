<?php

declare(strict_types=1);

namespace Athenaeum\Core;

use Closure;

/**
 * Minimal but capable router: named routes, `{param}` placeholders, per-route
 * middleware and automatic 404/405 handling.
 */
final class Router
{
    /** @var array<int,array{method:string,regex:string,params:string[],handler:mixed,name:?string,middleware:string[]}> */
    private array $routes = [];

    /** @var array<string,string> name → path pattern */
    private array $names = [];

    /** @var string[] */
    private array $groupMiddleware = [];

    private string $groupPrefix = '';

    public function get(string $path, mixed $handler, ?string $name = null): RouteDefinition
    {
        return $this->add('GET', $path, $handler, $name);
    }

    public function post(string $path, mixed $handler, ?string $name = null): RouteDefinition
    {
        return $this->add('POST', $path, $handler, $name);
    }

    public function any(string $path, mixed $handler, ?string $name = null): RouteDefinition
    {
        return $this->add('ANY', $path, $handler, $name);
    }

    /** @param string[] $middleware */
    public function group(array $middleware, Closure $callback, string $prefix = ''): void
    {
        $previousMiddleware = $this->groupMiddleware;
        $previousPrefix = $this->groupPrefix;
        $this->groupMiddleware = array_merge($this->groupMiddleware, $middleware);
        $this->groupPrefix = $previousPrefix . rtrim($prefix, '/');
        $callback($this);
        $this->groupMiddleware = $previousMiddleware;
        $this->groupPrefix = $previousPrefix;
    }

    private function add(string $method, string $path, mixed $handler, ?string $name): RouteDefinition
    {
        $full = $this->groupPrefix . ($path === '/' ? '' : $path);
        if ($full === '') {
            $full = '/';
        }

        $params = [];
        $regex = preg_replace_callback(
            // {name} matches one segment; {name:pattern} lets a route match more
            // (the vendor passthrough needs "pdfjs/web/viewer.html").
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/',
            static function (array $m) use (&$params): string {
                $params[] = $m[1];
                $pattern = isset($m[2]) && $m[2] !== '' ? $m[2] : '[^/]+';
                return '(?P<' . $m[1] . '>' . $pattern . ')';
            },
            $full
        ) ?? $full;

        $index = count($this->routes);
        $this->routes[$index] = [
            'method'     => $method,
            'regex'      => '#^' . $regex . '$#u',
            'params'     => $params,
            'handler'    => $handler,
            'name'       => $name,
            'middleware' => $this->groupMiddleware,
        ];

        if ($name !== null) {
            $this->names[$name] = $full;
        }

        return new RouteDefinition($this, $index, $full);
    }

    /** @param string[] $middleware */
    public function addMiddleware(int $index, array $middleware): void
    {
        $this->routes[$index]['middleware'] = array_merge($this->routes[$index]['middleware'], $middleware);
    }

    /** Used by the fluent ->name('x') helper. */
    public function registerName(string $name, string $path): void
    {
        $this->names[$name] = $path;
    }

    /** @return array<string,string> */
    public function names(): array
    {
        return $this->names;
    }

    /** Build a URL from a named route plus parameters. */
    public static function url(string $name, array $params = []): string
    {
        $router = App::router();
        $pattern = $router->names[$name] ?? null;
        if ($pattern === null) {
            return '/' . ltrim($name, '/');
        }
        // {name} and {name:pattern} are both placeholders; only the name part is
        // substituted. The pattern must not leak into the generated URL. A value
        // containing slashes (the vendor passthrough) keeps them readable.
        $url = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/',
            static function (array $m) use ($params): string {
                $value = (string) ($params[$m[1]] ?? '');
                return implode('/', array_map('rawurlencode', explode('/', $value)));
            },
            $pattern
        ) ?? $pattern;
        $query = array_diff_key($params, array_flip(
            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^}]+)?\}/', $pattern, $mm) ? $mm[1] : []
        ));
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }
        return $url;
    }

    public function dispatch(Request $request): Response
    {
        $path = $request->path();
        $method = $request->method();
        // HEAD is a GET without a body: the response layer already suppresses
        // the body, so route it like a GET instead of answering 405 (monitors,
        // crawlers and link checkers all use HEAD).
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            $pathMatched = true;
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                continue;
            }

            $params = [];
            foreach ($route['params'] as $name) {
                $params[$name] = $matches[$name] ?? '';
            }

            foreach ($route['middleware'] as $middleware) {
                $result = App::runMiddleware($middleware, $request, $params);
                if ($result instanceof Response) {
                    return $result;
                }
            }

            return App::callHandler($route['handler'], $request, $params);
        }

        if ($pathMatched) {
            return View::error(405, 'Method Not Allowed');
        }
        return View::error(404, 'Page not found');
    }
}

/** Fluent handle returned by Router::get/post so routes read nicely. */
final class RouteDefinition
{
    public function __construct(private Router $router, private int $index, private string $path)
    {
    }

    /** @param string|string[] $middleware */
    public function middleware(string|array $middleware): self
    {
        $this->router->addMiddleware($this->index, (array) $middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->router->registerName($name, $this->path);
        return $this;
    }
}
