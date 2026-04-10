<?php

declare(strict_types=1);

namespace Sloth\Route;

use Brain\Hierarchy\Hierarchy;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Illuminate\Support\Str;

use Sloth\Singleton\Singleton;

use stdClass;

use function FastRoute\simpleDispatcher;

/**
 * Router
 *
 * Handles HTTP routing for the Sloth framework using FastRoute.
 * Provides methods for defining routes and dispatching requests
 * to appropriate controllers.
 *
 * @since 1.0.0
 * @see https://github.com/nikic/FastRoute FastRoute library
 *
 * @example
 * ```php
 * Route::get('/about', ['controller' => 'PageController', 'action' => 'about']);
 * Route::post('/contact', ['controller' => 'ContactController', 'action' => 'submit']);
 * Route::any('/api/{id}', ['controller' => 'ApiController']);
 * ```
 */
final class Route extends Singleton
{
    /**
     * Flag indicating if routes have been dispatched.
     *
     * Once dispatched, no new routes can be added.
     *
     * @since 1.0.0
     */
    private static bool $dispatched = false;

    /**
     * The FastRoute dispatcher instance.
     *
     * @since 1.0.0
     */
    private static ?Dispatcher $dispatcher = null;

    /**
     * Collection of registered routes.
     *
     * Each route is stored as an array with keys:
     * - httpMethod: string|array<string>
     * - route: string (normalized)
     * - template: array (controller/action)
     *
     * @since 1.0.0
     * @var array<int, array{httpMethod: string|array<string>, route: string, template: array}>
     */
    private static array $routes = [];

    /**
     * Singleton instance of the Route class.
     *
     * @since 1.0.0
     */
    protected static ?Route $instance = null;

    /**
     * Prefix for custom WordPress rewrite tags.
     *
     * Used to identify routes registered by Sloth in WordPress.
     *
     * @since 1.0.0
     */
    protected string $rewriteTagPrefix = 'sloth';

    /**
     * Collection of compiled route regex patterns.
     *
     * These are used to register rewrite rules with WordPress.
     *
     * @since 1.0.0
     * @var array<int, string>
     */
    protected array $regexes = [];

    /**
     * Default values for route targets.
     *
     * Used when controller or action is not specified.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $routeTargetDefaults = [
        'controller' => \Sloth\Controller\Controller::class,
        'action' => 'index',
    ];

    /**
     * Retrieves the singleton Route instance.
     *
     * @return Route The singleton Route instance
     * @since 1.0.0
     *
     */
    public static function instance(): Route
    {
        if (is_null(static::$instance)) {
            static::$instance = new self();
        }

        return static::$instance;
    }

    /**
     * Boots the router and compiles all registered routes.
     *
     * This method should be called during theme initialization.
     * It compiles routes using FastRoute's cached dispatcher.
     *
     * @throws \Exception If cache directory is not writable
     *
     * @since 1.0.0
     *
     * @see simpleDispatcher For route compilation
     */
    public function boot(): void
    {
        if (self::$routes === []) {
            return;
        }

        $cacheDir = DIR_CACHE . DS . 'Route';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        self::$dispatcher = simpleDispatcher(function (RouteCollector $r): void {
            foreach (self::$routes as $route) {
                $r->addRoute(
                    $route['httpMethod'],
                    $route['route'],
                    $route['template']
                );
            }

            [$static, $variable] = $r->getData();

            foreach ($static as $routes) {
                foreach ($routes as $route => $template) {
                    $this->regexes[] = $this->getRewriteRuleRegex($route);
                }
            }

            foreach ($variable as $routes) {
                foreach ($routes as $route) {
                    $this->regexes[] = $this->getRewriteRuleRegex($route['regex']);
                }
            }
        }, [
            'cacheFile' => DIR_CACHE . DS . 'Route' . DS . 'route.php',
            'cacheDisabled' => WP_DEBUG,
        ]);
    }

    /**
     * Adds a route to the initial collection.
     *
     * @param string|array<string> $httpMethod The HTTP method(s) for the route
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action (controller, action)
     *
     * @throws \Exception If routes have already been dispatched
     * @since 1.0.0
     *
     */
    private static function addRoute(string|array $httpMethod, string $route, array $action): void
    {
        if (self::$dispatched) {
            throw new \Exception(
                "Adding Routes is no longer possible. Please use your template's routes.php to define Routes."
            );
        }

        self::$routes[] = [
            'httpMethod' => $httpMethod,
            'route' => self::normalize($route),
            'template' => $action,
        ];
    }

    /**
     * Registers a route for GET and POST methods.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     *
     * @throws \Exception
     * @example Route::add('/contact', ['controller' => 'ContactController']);
     * @since 1.0.0
     *
     */
    public static function add(string $route, array $action): void
    {
        self::addRoute(['GET', 'POST'], $route, $action);
    }

    /**
     * Registers a route for GET requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     *
     * @throws \Exception
     * @example Route::get('/about', ['controller' => 'PageController', 'action' => 'about']);
     * @since 1.0.0
     *
     */
    public static function get(string $route, array $action): void
    {
        self::addRoute('GET', $route, $action);
    }

    /**
     * Registers a route for POST requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     *
     * @throws \Exception
     * @example Route::post('/contact', ['controller' => 'ContactController', 'action' => 'submit']);
     * @since 1.0.0
     *
     */
    public static function post(string $route, array $action): void
    {
        self::addRoute('POST', $route, $action);
    }

    /**
     * Registers a route for PUT requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     * @throws \Exception
     * @since 1.0.0
     *
     */
    public static function put(string $route, array $action): void
    {
        self::addRoute('PUT', $route, $action);
    }

    /**
     * Registers a route for PATCH requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     * @throws \Exception
     * @since 1.0.0
     *
     */
    public static function patch(string $route, array $action): void
    {
        self::addRoute('PATCH', $route, $action);
    }

    /**
     * Registers a route for DELETE requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     * @throws \Exception
     * @since 1.0.0
     *
     */
    public static function delete(string $route, array $action): void
    {
        self::addRoute('DELETE', $route, $action);
    }

    /**
     * Registers a route for HEAD requests.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     * @throws \Exception
     * @since 1.0.0
     *
     */
    public static function head(string $route, array $action): void
    {
        self::addRoute('HEAD', $route, $action);
    }

    /**
     * Registers a route for all HTTP methods.
     *
     * @param string $route The route pattern
     * @param array<string, mixed> $action The route action
     *
     * @throws \Exception
     * @example Route::any('/api/{id}', ['controller' => 'ApiController']);
     * @since 1.0.0
     *
     */
    public static function any(string $route, array $action): void
    {
        self::addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'], $route, $action);
    }

    /**
     * Normalizes a route pattern for FastRoute.
     *
     * Adds optional trailing slash handling and ensures routes
     * don't break when WordPress adds trailing slashes.
     *
     * @param string $route The raw route pattern
     *
     * @return string The normalized route pattern
     *
     * @since 1.0.0
     *
     * @example '/about' becomes '/about[/]'
     * @example '/blog/{slug}' becomes '/blog/{slug}[/]'
     */
    private static function normalize(string $route): string
    {
        if (str_ends_with($route, ']')) {
            return substr($route, 0, -1) . '[/]]';
        }

        return rtrim($route, '/') . '[/]';
    }

    /**
     * Dispatches the current request to the appropriate controller.
     *
     * This method is called during WordPress's template_redirect hook.
     * It attempts to match the current URI against registered routes,
     * falling back to WordPress's template hierarchy if no match is found.
     *
     * @since 1.0.0
     *
     * @global \WP_Query $wp_query The WordPress query object
     * @global \WP $wp The WordPress main object
     * @global \WP_Post|null $post The current post object
     */
    public function dispatch(): void
    {
        global $wp_query, $wp, $post;

        if (!self::$dispatcher instanceof \FastRoute\Dispatcher) {
            return;
        }

        self::$dispatched = true;

        $httpMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        if (false !== $pos = strpos((string) $uri, '?')) {
            $uri = substr((string) $uri, 0, $pos);
        }

        $uri = rawurldecode((string) $uri);
        $routeTarget = [];

        $routeInfo = self::$dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                $hierarchy = new Hierarchy();
                $templates = $hierarchy->getTemplates($wp_query);
                if (($templates[0] ?? '') !== '404') {
                    foreach ($templates as $template) {
                        $myController = $this->getController($template);
                        if (class_exists($myController)) {
                            $routeTarget = [
                                'controller' => $myController,
                                'action' => 'index',
                            ];
                            break;
                        }
                    }
                }

                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $allowedMethods = $routeInfo[1];
                break;

            case Dispatcher::FOUND:
                $routeTarget = $routeInfo[1];
                $routeTarget['controller'] = $this->getController($routeTarget['controller']);
                $wp->query_vars = $routeInfo[2];
                break;
        }

        if (!isset($routeTarget['action'])) {
            $routeTarget['action'] = 'index';
        }

        $routeInfo[2] ??= [];

        if (isset($routeTarget['controller']) && class_exists($routeTarget['controller'])) {
            $request = new StdClass();
            $request->params = [
                'action' => $routeTarget['action'],
                'pass' => (array)$routeInfo[2],
            ];
            $controller = new $routeTarget['controller']();
            $controller->invokeAction($request);
            exit;
        }

        if (isset($wp_query->query['page'])) {
            $currentPage = $wp_query->query['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(
                static fn(): int|string => $currentPage
            );
        }
    }

    /**
     * Resolves the controller class name from a template name.
     *
     * Converts template names like 'about-us' to controller names
     * like 'Theme\Controller\AboutUsController'.
     *
     * @param string $name The template name
     *
     * @return string The fully qualified controller class name
     *
     * @since 1.0.0
     *
     * @example 'about-us' becomes 'Theme\Controller\AboutUsController'
     */
    private function getController(string $name): string
    {
        $name = Str::studly($name);

        if (!Str::endsWith($name, 'Controller')) {
            $name .= 'Controller';
        }

        if (str_contains($name, '\\')) {
            return $name;
        }

        return 'Theme\\Controller\\' . $name;
    }

    /**
     * Converts a FastRoute regex to a WordPress rewrite rule regex.
     *
     * FastRoute uses a different regex format than WordPress rewrite rules.
     * This method converts between the two formats.
     *
     * @param string $routeRegex The FastRoute regex pattern
     *
     * @return string The WordPress-compatible regex pattern
     *
     * @since 1.0.0
     *
     * @example '~^/about/$~s' becomes '^about/$~' for WordPress
     */
    private function getRewriteRuleRegex(string $routeRegex): string
    {
        if (str_starts_with($routeRegex, '~')) {
            $routeRegex = preg_replace('/^\~\^/', '^', $routeRegex);
            $routeRegex = preg_replace('/\$\~$/', '', (string) $routeRegex);
        } else {
            $routeRegex = preg_replace('/^\//', '^', $routeRegex);
            $routeRegex = preg_replace('/\/$/', '', (string) $routeRegex);
            $routeRegex .= '/$';
        }

        return $routeRegex;
    }

    /**
     * Registers WordPress rewrite rules for all routes.
     *
     * This allows WordPress to recognize Sloth routes and pass
     * them to the router instead of 404ing.
     *
     * @since 1.0.0
     *
     * @uses add_rewrite_tag() To register custom query vars
     * @uses add_rewrite_rule() To register route patterns
     */
    public function setRewrite(): void
    {
        $regexes = array_unique($this->regexes);

        foreach ($regexes as $regex) {
            add_rewrite_tag('%is_sloth_route%', '(\d)');
            add_rewrite_rule($regex, 'index.php?is_sloth_route=1', 'top');
        }
    }

    public function flushRewriteRules(): void
    {
        flush_rewrite_rules();
    }
}
