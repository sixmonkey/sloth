<?php

namespace Sloth\Deployment;

use Sloth\Core\ServiceProvider;

class DeploymentServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'deployment',
        ];
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton(
            'deployment',
            function ($container) {
                return Deployment::instance();
            }
        );
    }
}
