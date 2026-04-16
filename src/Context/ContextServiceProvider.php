<?php

namespace Sloth\Context;

use Sloth\Core\ServiceProvider;

class ContextServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('context', function () {
            return new Context();
        });
    }
}
