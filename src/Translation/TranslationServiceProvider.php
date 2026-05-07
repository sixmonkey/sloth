<?php

declare(strict_types=1);
namespace Sloth\Translation;

use function get_locale;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Override;
use Sloth\Core\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(
            'translator',
            function ($container): Factory {
                $loader = new ArrayLoader();

                return new Factory(
                    new Translator($loader, get_locale()),
                    $container,
                );
            },
        );
    }
}
