<?php

namespace Sloth\Configure;

use Sloth\Core\ServiceProvider;

class ConfigureServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            'configure',
            function ($container) {
                return Configure::getInstance();
            }
        );
    }
}
