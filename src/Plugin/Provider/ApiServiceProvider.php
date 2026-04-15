<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Core\ServiceProvider;
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
 * @since 1.0.0
 * @see \Sloth\Api\Controller
 * @see \Sloth\Plugin\Plugin
 */
class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register API controllers hooks.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'rest_api_init' => fn() => $this->registerControllers(),
        ];
    }

    /**
     * Register API controllers filters.
     *
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        return [
            'rest_post_dispatch' => fn($response) => $response,
        ];
    }

    /**
     * Register API controllers from DIR_APP/Api/.
     *
     * @since 1.0.0
     *
     * @throws \Exception If a controller doesn't extend \Sloth\Api\Controller
     * @see \Sloth\Api\Controller For controller base class requirements
     */
    public function registerControllers(): void
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
     * @since 1.0.0
     *
     * @param object $controller The controller instance
     */
    protected function registerControllerRoutes(object $controller): void
    {
        $methods = get_class_methods($controller);
        $routePrefix = Utility::viewize(new \ReflectionClass($controller)->getShortName());
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

        foreach ($routes as $route => $action) {
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
        }
    }

    /**
     * Load a class from a file.
     *
     * @since 1.0.0
     *
     * @param string $file Absolute path to the PHP file
     *
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
