<?php

namespace Sloth\Translation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(
            'translator',
            function ($container) {
                $loader  = new ArrayLoader();
                $factory = new Factory(
                    new Translator($loader, \get_locale()),
                    $container
                );

                return $factory;
            }
        );
    }
}
