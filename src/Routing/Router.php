<?php

declare(strict_types=1);

namespace Sloth\Routing;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * Laravel-esque router backed by Symfony Routing.
 *
 * Routes are registered in routes/web.php and dispatched
 * on the WordPress template_redirect hook.
 *
 * ## Usage (routes/web.php)
 *
 * ```php
 * use Sloth\Facades\Route;
 * use Sloth\Facades\Response;
 *
 * Route::get('/css/products', function () {
 *     return Response::make(view('styles.index'), 200)
 *         ->header('Content-Type', 'text/css');
 * })->name('product-styles');
 * ```
 *
 * @since 1.0.0
 */
class Router
{
    private RouteCollection $routes;
    private int $counter = 0;

    /**
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->routes = new RouteCollection();
    }

    /**
     * Register a GET route.
     *
     * @param string $path
     * @param callable|array $callback
     * @since 1.0.0
     */
    public function get(string $path, mixed $callback): Route
    {
        return $this->add('GET', $path, $callback);
    }

    /**
     * Register a POST route.
     *
     * @since 1.0.0
     */
    public function post(string $path, mixed $callback): Route
    {
        return $this->add('POST', $path, $callback);
    }

    /**
     * Register a PUT route.
     *
     * @since 1.0.0
     */
    public function put(string $path, mixed $callback): Route
    {
        return $this->add('PUT', $path, $callback);
    }

    /**
     * Register a DELETE route.
     *
     * @since 1.0.0
     */
    public function delete(string $path, mixed $callback): Route
    {
        return $this->add('DELETE', $path, $callback);
    }

    /**
     * Check if a named route exists.
     *
     * @since 1.0.0
     */
    public function hasName(string $name): bool
    {
        return $this->routes->get($name) !== null;
    }

    /**
     * Match a path and HTTP method against registered routes.
     *
     * Returns Symfony's parameter array on match — includes
     * _controller (the callback), _route (the route name),
     * and any URL parameters. Returns null if no route matches.
     *
     * @return array<string, mixed>|null
     * @since 1.0.0
     */
    public function match(string $path, string $method): ?array
    {
        $context = new RequestContext('', strtoupper($method));
        $matcher = new UrlMatcher($this->routes, $context);

        try {
            return $matcher->match($path);
        } catch (ResourceNotFoundException|MethodNotAllowedException) {
            return null;
        }
    }

    /**
     * Generate a URL for a named route.
     *
     * @param array<string, string> $params
     * @throws \InvalidArgumentException If route name does not exist.
     * @since 1.0.0
     */
    public function url(string $name, array $params = []): string
    {
        if ($this->routes->get($name) === null) {
            throw new \InvalidArgumentException(
                "Route [{$name}] not defined."
            );
        }

        return new UrlGenerator($this->routes, new RequestContext())
            ->generate($name, $params);
    }

    /**
     * Register a name for a route. Called by Route::name().
     *
     * @internal
     * @since 1.0.0
     */
    public function registerName(string $name, Route $route): void
    {
        foreach ($this->routes->all() as $key => $r) {
            if ($r === $route) {
                $this->routes->remove($key);
                $this->routes->add($name, $route);
                return;
            }
        }
    }

    /**
     * @since 1.0.0
     */
    private function add(string $method, string $path, mixed $callback): Route
    {
        $route = new Route($path, $callback, [$method], $this);
        $this->routes->add('_route_' . $this->counter++, $route);

        return $route;
    }
}
