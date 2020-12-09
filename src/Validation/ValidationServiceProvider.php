<?php

namespace Sloth\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;

class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

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
            'validator',
            function ($app) {
                $validator = new Factory(new Translator(new ArrayLoader(), \get_locale()), $app);


                return $validator;
            }
        );
    }
}
