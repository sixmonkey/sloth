<?php

declare(strict_types=1);

namespace Sloth\Route;

use Sloth\Core\ServiceProvider;
use Sloth\Core\Application;

/**
 * Route Service Provider
 *
 * Registers the Route singleton with the application container.
 *
 * @since 1.0.0
 * @see ServiceProvider For the base class
 * @see Route For the route implementation
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Registers services with the container.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(
            'route',
            static fn(Application $container): Route => Route::instance()
        );
    }
}
