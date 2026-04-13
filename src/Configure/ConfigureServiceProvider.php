<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Configure component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ConfigureServiceProvider extends ServiceProvider
{
    /**
     * Register the Configure service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'configure',
            fn($container): \Sloth\Configure\Configure => Configure::getInstance()
        );
    }
}
