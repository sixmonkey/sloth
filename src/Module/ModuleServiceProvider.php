<?php

namespace Sloth\Module;

use Sloth\Core\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->bind(
            'module',
            function () {
                return new Module();
            }
        );
    }
}
