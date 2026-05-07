<?php

declare(strict_types=1);
namespace Sloth\Api;

use function register_rest_route;
use Override;
use Sloth\Api\Manifest\ApiControllerManifestBuilder;
use Sloth\Core\ServiceProvider;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Service provider for REST API controller registration.
 *
 * Handles the full lifecycle of Sloth API controllers: discovery, manifest
 * generation, and automatic route registration under the `sloth/v1/` namespace.
 *
 * ## Discovery
 *
 * ApiControllerManifestBuilder scans app/Api/ and theme/Api/ for classes
 * extending Sloth\Api\Controller. Each controller's public methods are
 * analyzed at build time to determine available routes.
 *
 * ## Route mapping
 *
 * Controller methods are automatically mapped to REST routes:
 * - **Public methods** (not starting with `_`, not `single`) become route
 *   actions: `/sloth/v1/{controller}/{method}[/{id}]`
 * - **single() method**: If present, triggers special routing:
 *   - `/{controller}` → `index()`
 *   - `/{controller}[/{id}]` → `single()`
 * - **No single()**: `/{controller}[/{id}]` → `index()`
 *
 * Route names are derived from class/method names via Utility::viewize()
 * (e.g. `NewsController::getFeatured()` → `news/get-featured`).
 *
 * ## Request handling
 *
 * Each route callback:
 * 1. Instantiates the controller and sets the WP_REST_Request.
 * 2. Extracts the `id` URL parameter and passes it to the action method.
 * 3. Returns a WP_REST_Response with the controller's response status
 *    and headers. Error responses include HTTP status text.
 *
 * ## Supported HTTP methods
 *
 * All routes accept GET, POST, DELETE, and PUT. The controller's action
 * method receives the full request object and can inspect the method.
 *
 * ## Hook execution order
 *
 * 1. `init` → ApiControllerManifestBuilder::init() (discovery + manifest)
 * 2. `rest_api_init` → registerControllers() (route registration)
 * 3. `rest_post_dispatch` → passthrough filter (reserved for response manipulation)
 *
 * ## Container bindings
 *
 * No explicit container bindings are created. The manifest entry data is
 * accessed directly from ApiControllerManifestBuilder::getEntries().
 *
 * @since 1.0.0
 * @see Controller                          For the controller base class
 * @see ApiControllerManifestBuilder For controller discovery
 */
class ApiServiceProvider extends ServiceProvider
{
    /**
     * Register the API controller manifest builder.
     *
     * Binds ApiControllerManifestBuilder as a singleton so the entry data
     * computed during init() is available to registerControllers().
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(ApiControllerManifestBuilder::class, fn ($app): ApiControllerManifestBuilder => new ApiControllerManifestBuilder($app));
    }

    /**
     * Register WordPress action hooks for API controller management.
     *
     * Returns an array of hook => callback mappings:
     * - **init**: Runs ApiControllerManifestBuilder::init() for discovery.
     * - **rest_api_init**: Calls registerControllers() to register REST routes.
     *
     * @return array<string, array<callable>|callable> hook mappings
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        return [
            'init'          => $this->initControllers(...),
            'rest_api_init' => $this->registerControllers(...),
        ];
    }

    /**
     * Register WordPress filter hooks for API response handling.
     *
     * Returns an array of filter => callback mappings:
     * - **rest_post_dispatch**: Passthrough filter, reserved for future
     *   response manipulation (e.g. adding custom headers).
     *
     * @return array<string, array<callable>|callable> filter mappings
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        return [
            'rest_post_dispatch' => fn ($response) => $response,
        ];
    }

    /**
     * Initialize API controllers: run discovery and load the manifest.
     *
     * Calls ApiControllerManifestBuilder::init() which discovers Controller
     * subclasses, computes route information, and writes/loads the manifest.
     *
     * @since 1.0.0
     */
    protected function initControllers(): void
    {
        app(ApiControllerManifestBuilder::class)->init();
    }

    /**
     * Register all discovered API controllers as REST routes.
     *
     * Iterates over the manifest entries and calls registerControllerRoutes()
     * for each controller. Routes are registered under the `sloth/v1/`
     * namespace.
     *
     * @since 1.0.0
     * @see Controller For controller base class requirements
     */
    public function registerControllers(): void
    {
        foreach (app(ApiControllerManifestBuilder::class)->getEntries() as $controllerClass => $entry) {
            $this->registerControllerRoutes($controllerClass, $entry);
        }
    }

    /**
     * Register REST routes for a single controller.
     *
     * Builds the route map based on the controller's pre-computed entry data:
     * - Standard methods: `{routePrefix}/{method}[/{id}]`
     * - With single(): `{routePrefix}` → index, `{routePrefix}[/{id}]` → single
     * - Without single(): `{routePrefix}[/{id}]` → index
     *
     * Each route callback instantiates the controller, sets the request,
     * extracts the `id` parameter, and delegates to the action method.
     *
     * @param class-string<Controller>                                           $controllerClass the controller class to register
     * @param array{routePrefix: string, methods: list<string>, hasSingle: bool} $entry
     *                                                                                            Pre-computed route data
     *
     * @since 1.0.0
     */
    protected function registerControllerRoutes(string $controllerClass, array $entry): void
    {
        $routePrefix = $entry['routePrefix'];
        $routes = [];

        foreach ($entry['methods'] as $method) {
            $routes[$routePrefix . '/' . \Sloth\Utility\Utility::viewize($method) . '(?:/(?P<id>\w+))?'] = $method;
        }

        if ($entry['hasSingle']) {
            $routes[$routePrefix] = 'index';
            $routes[$routePrefix . '(?:/(?P<id>.+))?'] = 'single';
        } else {
            $routes[$routePrefix . '(?:/(?P<id>.+))?'] = 'index';
        }

        foreach ($routes as $route => $action) {
            register_rest_route(
                'sloth/v1',
                '/' . $route,
                [
                    'methods'  => ['GET', 'POST', 'DELETE', 'PUT'],
                    'callback' => function (WP_REST_Request $request) use ($controllerClass, $action): WP_REST_Response {
                        $controller = new $controllerClass();
                        $controller->setRequest($request);
                        $param = $request->get_url_params('id');
                        $data = call_user_func_array([$controller, $action], [reset($param)]);

                        if (empty($data) && $controller->response->status >= 400) {
                            $data = [
                                'code'    => $controller->response->status,
                                'message' => \Symfony\Component\HttpFoundation\Response::$statusTexts[$controller->response->status] ?? 'Unknown Error',
                            ];
                        }

                        return new WP_REST_Response(
                            $data,
                            $controller->response->status,
                            $controller->response->headers,
                        );
                    },
                ],
            );
        }
    }
}
