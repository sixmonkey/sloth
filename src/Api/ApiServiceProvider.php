<?php

declare(strict_types=1);

namespace Sloth\Api;

use Sloth\Api\Manifest\ApiControllerManifestBuilder;
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
 */
class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register the API controller manifest builder.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(ApiControllerManifestBuilder::class, fn($app) => new ApiControllerManifestBuilder($app));
    }

    /**
     * Register API controllers hooks.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'init' => fn() => app(ApiControllerManifestBuilder::class)->init(),
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
     * @throws \Exception If a controller doesn't extend \Sloth\Api\Controller
     * @since 1.0.0
     *
     * @see \Sloth\Api\Controller For controller base class requirements
     */
    public function registerControllers(): void
    {
        collect(app('sloth.api-controllers'))
            ->each(fn($controller) => $this->registerControllerRoutes($controller));
    }

    /**
     * Register REST routes for a controller.
     *
     * @param object $controllerClass The controller instance
     * @since 1.0.0
     *
     */
    protected function registerControllerRoutes($controllerClass): void
    {
        $reflection = new \ReflectionClass($controllerClass);
        $methods = $reflection->getMethods();
        $routePrefix = Utility::viewize($reflection->getShortName());
        $routes = [];
        foreach ($methods as $method) {
            $name = $method->name;

            if (str_starts_with($name, '_')) {
                continue;
            }
            if ($name === 'single') {
                continue;
            }
            $routes[$routePrefix . '/' . Utility::viewize($name) . '(?:/(?P<id>\w+))?'] = $name;
        }
# @TODO maybe check, if a controller action accepts any params and suppress nested route registration?
        if (method_exists($controllerClass, 'single')) {
            $routes[$routePrefix] = 'index';
            $routes[$routePrefix . '(?:/(?P<id>.+))?'] = 'single';
        } else {
            $routes[$routePrefix . '(?:/(?P<id>.+))?'] = 'index';
        }

        foreach ($routes as $route => $action) {
            \register_rest_route(
                'sloth/v1',
                '/' . $route,
                [
                    'methods' => ['GET', 'POST', 'DELETE', 'PUT'],
                    'callback' => function (WP_REST_Request $request) use ($controllerClass, $action) {
                        $controller = new $controllerClass();
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
}
