<?php

declare(strict_types=1);

namespace App;

use App\Middleware\MiddlewareInterface;

/**
 * Nizam -- router (§C: front controller / router foundation). Extended in
 * Phase 6 to support "{id}"-style path segments -- Phase 4/5's version only
 * ever needed exact literal paths, and its own docblock said as much
 * ("Phase 6's per-record routes ... will extend this when that phase
 * actually builds them, not before"). "{id}" specifically means \d+ (every
 * route's PK segment is an INT UNSIGNED id) -- a non-numeric segment simply
 * doesn't match and falls through to another route or a clean 404. Every
 * OTHER param name (Phase 7's "{key}"/"{format}" -- a report key like
 * "class-list", an export format like "excel") matches a lowercase slug
 * [a-z0-9_-]+ instead, since those are never numeric PKs. Unlike "{id}",
 * which can never collide with a literal route ("create" doesn't match
 * \d+), a slug segment COULD in principle match a literal sibling route's
 * own text -- not actually reachable today (no literal route shares a
 * "{key}" route's depth), but worth knowing if one is ever added: register
 * the more specific literal route first.
 */
final class Router
{
    /** @var array<string, array<int, array{pattern: string, handler: array{0: class-string, 1: string}, middleware: MiddlewareInterface[]}>> */
    private array $routes = [];

    /** @param MiddlewareInterface[] $middleware */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->routes['GET'][] = ['pattern' => $path, 'handler' => $handler, 'middleware' => $middleware];
    }

    /** @param MiddlewareInterface[] $middleware */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->routes['POST'][] = ['pattern' => $path, 'handler' => $handler, 'middleware' => $middleware];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes[$request->method()] ?? [] as $route) {
            $params = self::match($route['pattern'], $request->path());
            if ($params === null) {
                continue;
            }

            $request->setRouteParams($params);

            foreach ($route['middleware'] as $middleware) {
                if (!$middleware->handle($request)) {
                    return; // the middleware already sent a complete response
                }
            }

            [$controllerClass, $method] = $route['handler'];
            $controller = new $controllerClass();
            $controller->$method($request);
            return;
        }

        ErrorHandler::renderNotFound();
    }

    /** @return array<string,string>|null null if the path doesn't match this pattern */
    private static function match(string $pattern, string $path): ?array
    {
        if (!str_contains($pattern, '{')) {
            return $pattern === $path ? [] : null;
        }

        $regex = preg_replace_callback(
            '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
            static fn (array $m): string => $m[1] === 'id' ? '(?P<id>\d+)' : "(?P<{$m[1]}>[a-z0-9_-]+)",
            $pattern
        );
        if ($regex === null || !preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }
}
