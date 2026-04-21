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
     */
    protected bool $defer = false;

    /**
     * Register the service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'deployment',
            fn($container): \Sloth\Deployment\Deployment => new Deployment()
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<string>
     * @since 1.0.0
     *
     */
    #[\Override]
    public function provides(): array
    {
        return [
            'deployment',
        ];
    }
}
