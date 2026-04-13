<?php

declare(strict_types=1);

namespace Sloth\Request;

use Illuminate\Http\Request;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Request component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class RequestServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'request',
            fn($app) => Request::capture()
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    #[\Override]
    public function provides(): array
    {
        return [
            'request',
        ];
    }
}
