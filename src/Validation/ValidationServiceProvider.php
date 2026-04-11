<?php

declare(strict_types=1);

namespace Sloth\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Validation component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ValidationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @since 1.0.0
     */
    protected bool $defer = true;

    /**
     * Register the service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(
            'validator',
            fn($app): \Illuminate\Validation\Factory => new Factory(
                new Translator(new ArrayLoader(), \get_locale()),
                $app
            )
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'validator',
        ];
    }
}
