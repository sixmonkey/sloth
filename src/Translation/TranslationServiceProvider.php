<?php

namespace Sloth\Translation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;
use Illuminate\Events\Dispatcher;

class TranslationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            'translator',
            function ($container): \Illuminate\Validation\Factory {
                $loader  = new ArrayLoader();

                return new Factory(
                    new Translator($loader, \get_locale()),
                    $container
                );
            }
        );
    }
}
