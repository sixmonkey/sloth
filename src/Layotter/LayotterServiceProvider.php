<?php

declare(strict_types=1);

namespace Sloth\Layotter;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Layotter component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class LayotterServiceProvider extends ServiceProvider
{
    /**
     * Register the Layotter service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(
            'layotter',
            fn($container): \Sloth\Layotter\Layotter => Layotter::getInstance()
        );
    }
}
