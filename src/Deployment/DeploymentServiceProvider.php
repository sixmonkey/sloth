<?php

declare(strict_types=1);

namespace Sloth\Deployment;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Deployment component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class DeploymentServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @since 1.0.0
     * @var bool
     */
    protected bool $defer = false;

    /**
     * Register the service provider.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(
            'deployment',
            fn($container) => Deployment::getInstance()
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'deployment',
        ];
    }
}
