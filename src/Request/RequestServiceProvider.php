<?php

namespace Sloth\Request;

use Illuminate\Http\Request;
use Sloth\Core\ServiceProvider;

class RequestServiceProvider extends ServiceProvider
{
    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            'validator',
        ];
    }
    /**
     * Register the service provider.
     */
    public function register()
    {
        $this->app->singleton(
            'request',
            function ($app) {
                $request = Request::capture();

                return $request;
            }
        );
    }
}
