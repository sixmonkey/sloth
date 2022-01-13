<?php

namespace Sloth\Layotter;

use Sloth\Core\ServiceProvider;

class LayotterServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            'layotter',
            function ($container) {
                return Layotter::getInstance();
            }
        );
    }
}
