<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Utility\Utility;
use Symfony\Component\HttpFoundation\Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Service provider for REST API controller registration.
 *
 * Handles:
 * - Discovery of API controller classes from DIR_APP/Api/
 * - Auto-mapping of public methods to REST routes under /sloth/v1/
 * - Error handling with warnings surfacing in dev environments
 *
 * ## Route Auto-mapping
 *
 * Discovers controller classes and automatically maps their public
 * methods to REST routes:
 * - Method `index()` creates route: GET /sloth/v1/{controller}
 * - Method `single(id)` creates route: GET /sloth/v1/{controller}/{id}
 * - Other methods create route: GET /sloth/v1/{controller}/{method}/{id?}
 *
 * Methods starting with `_` are skipped.
 *
 * ## Controller Requirements
 *
 * Controllers must extend \Sloth\Api\Controller which provides:
 * - setRequest() to receive the WP_REST_Request
 * - response property for status codes and headers
 *
 * ## Error Handling
 *
 * PHP warnings from libraries like Corcel are captured and surfaced
 * as a `_warnings` key in the JSON response in development environments.
 * This prevents corrupted JSON responses while still providing debugging info.
 *
 * @since 1.0.0
 * @see \Sloth\Api\Controller
 * @see \Sloth\Plugin\Plugin
 */
class ApiServiceProvider
{
    /**
     * Whether this is a development environment.
     *
     * @var bool
     */
    protected bool $isDevEnv;

    /**
     * Create a new API service provider instance.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->isDevEnv = in_array(WP_ENV ?? '', ['development', 'develop', 'dev'], true);
    }

    /**
     * Register API controllers from DIR_APP/Api/.
     *
     * Discovers controller classes from DIR_APP/Api/, validates they
     * extend \Sloth\Api\Controller, and registers REST routes for
     * their public methods.
     *
     * ## Route Generation
     *
     * For each controller found:
     * 1. Load and instantiate the controller
     * 2. Validate it extends \Sloth\Api\Controller
     * 3. Map public methods to REST routes
     * 4. Register routes via register_rest_route()
     *
     * @since 1.0.0
     *
     * @throws \Exception If a controller doesn't extend \Sloth\Api\Controller
     * @see \Sloth\Api\Controller For controller base class requirements
     */
    public function register(): void
    {
        foreach (glob(DIR_APP . 'Api' . DS . '*.php') as $file) {
            $controllerName = $this->loadClassFromFile($file);

            $controller = new $controllerName();

            if (!is_subclass_of($controller, \Sloth\Api\Controller::class)) {
                throw new \Exception("ApiController {$controllerName} needs to extend Sloth\\Api\\Controller");
            }

            $this->registerControllerRoutes($controller);
        }
    }

    /**
     * Register REST routes for a controller.
     *
     * Generates and registers REST routes based on the controller's
     * public methods. Also registers the rest_post_dispatch filter
     * for error handling.
     *
     * @since 1.0.0
     *
     * @param object $controller The controller instance
     */
    protected function registerControllerRoutes(object $controller): void
    {
        $methods = get_class_methods($controller);
        $routePrefix = Utility::viewize((new \ReflectionClass($controller))->getShortName());
        $routes = [];

        foreach ($methods as $method) {
            if (str_starts_with($method, '_')) {
                continue;
            }
            if ($method === 'single') {
                continue;
            }
            $routes[$routePrefix . '/' . Utility::viewize($method) . '(?:/(?P<id>\w+))?'] = $method;
        }

        if (method_exists($controller, 'single')) {
            $routes[$routePrefix] = 'index';
            $routes[$routePrefix . '(?:/(?P<id>[a-z0-9._-]+))?'] = 'single';
        } else {
            $routes[$routePrefix . '(?:/(?P<id>[a-z0-9._-]+))?'] = 'index';
        }

        add_filter('rest_post_dispatch', fn($response) => $response);

        foreach ($routes as $route => $action) {
            add_action('rest_api_init', function () use ($route, $action, $controller): void {
                register_rest_route(
                    'sloth/v1',
                    '/' . $route,
                    [
                        'methods' => ['GET', 'POST', 'DELETE', 'PUT'],
                        'callback' => function (WP_REST_Request $request) use ($controller, $action) {
                            $controller->setRequest($request);
                            $param = $request->get_url_params('id');
                            $data = call_user_func_array([$controller, $action], [reset($param)]);

                            if (empty($data) && $controller->response->status >= 400) {
                                $data = [
                                    'code' => $controller->response->status,
                                    'message' => Response::$statusTexts[$controller->response->status] ?? 'Unknown Error',
                                ];
                            }

                            return new WP_REST_Response(
                                $data,
                                $controller->response->status,
                                $controller->response->headers
                            );
                        },
                    ]
                );
            });
        }
    }

    /**
     * Check if this is a development environment.
     *
     * @since 1.0.0
     *
     * @return bool True if in development mode
     */
    public function isDevEnv(): bool
    {
        return $this->isDevEnv;
    }

    /**
     * Load a class from a file.
     *
     * Includes a PHP file and uses reflection to find the class defined in it.
     * Skips Corcel namespace classes (handled by Corcel itself) and returns
     * the first matching App\ namespaced class.
     *
     * @since 1.0.0
     *
     * @param string $file Absolute path to the PHP file
     * @return string Class name if found, empty string otherwise
     */
    protected function loadClassFromFile(string $file): string
    {
        $file = realpath($file);
        include_once $file;

        $matchingClass = null;

        foreach (get_declared_classes() as $class) {
            $rc = new \ReflectionClass($class);
            if ($rc->getFilename() === $file) {
                if (str_starts_with($class, 'Corcel\\')) {
                    continue;
                }

                if (str_starts_with($class, 'App\\')) {
                    $matchingClass = $class;
                    break;
                }

                $matchingClass = $class;
            }
        }

        return $matchingClass ?? '';
    }
}
