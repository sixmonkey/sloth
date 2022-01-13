<?php

namespace Sloth\Route;

use Sloth\Core\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            'route',
            function ($container) {
                return Route::instance();
            }
        );
    }
}
