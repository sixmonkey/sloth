<?php

declare(strict_types=1);
namespace Sloth\Routing;

use Override;
use Sloth\Core\ServiceProvider;
use Sloth\Http\Response;
use Sloth\Routing\Manifest\RoutesManifestBuilder;

/**
 * Service provider for the Sloth Router.
 *
 * Registers the Router as a singleton, loads routes/web.php from
 * app/routes/ and theme/routes/, and dispatches matched routes on
 * the WordPress template_redirect hook.
 *
 * ## Route files
 *
 * Both locations are optional and loaded in order:
 * - app/routes/web.php
 * - theme/routes/web.php
 *
 * The $router variable is available inside each routes file.
 *
 * @since 1.0.0
 */
class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register the Router singleton and facade alias.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(Router::class, fn (): Router => new Router());
        $this->app->alias(Router::class, 'router');

        $this->app->singleton(
            UrlGenerator::class,
            fn ($app): UrlGenerator => new UrlGenerator($app, $app->make(Router::class)),
        );
        $this->app->alias(UrlGenerator::class, 'url');

        $this->app->singleton(
            RoutesManifestBuilder::class,
            fn ($app): RoutesManifestBuilder => new RoutesManifestBuilder($app),
        );
    }

    /**
     * Load route files from app/ and theme/.
     *
     * @since 1.0.0
     */
    #[Override]
    public function boot(): void
    {
        $this->app->make(RoutesManifestBuilder::class)->init();
    }

    /**
     * Register template_redirect hook for route dispatching.
     *
     * @return array<string, callable>
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        return [
            'template_redirect' => [
                'callback' => $this->dispatch(...),
                'priority' => 1,
            ],
        ];
    }

    /**
     * Dispatch the current request to a matching route.
     *
     * If a route matches, the _controller callback is executed.
     * Illuminate Response instances are sent properly. Strings
     * are echoed directly. Either way WordPress template loading stops.
     *
     * @since 1.0.0
     */
    protected function dispatch(): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $params = $this->app->make(Router::class)->match($path, $method);

        if ($params === null) {
            return;
        }

        $controller = $params['_controller'];
        $args = array_values(array_filter(
            $params,
            fn ($key): bool => !str_starts_with((string) $key, '_'),
            ARRAY_FILTER_USE_KEY,
        ));

        $response = $controller(...$args);

        if ($response instanceof Response) {
            $response->send();

            exit;
        }

        if (is_string($response)) {
            echo $response;

            exit;
        }

        exit;
    }
}
