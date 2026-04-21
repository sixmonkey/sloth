<?php

declare(strict_types=1);

namespace Sloth\Core;

use Sloth\Core\Manifest\IncludesManifestBuilder;
use Sloth\Core\Manifest\ProvidersManifestBuilder;

/**
 * Service provider for Application-level bootstrapping.
 *
 * Registers manifest builders for includes and service providers,
 * and hooks them into WordPress's init action.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ApplicationServiceProvider extends ServiceProvider
{

    /**
     * Register the Application service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(IncludesManifestBuilder::class, fn($app) => new IncludesManifestBuilder($app));
    }


    /**
     * Register hooks for model registration.
     *
     * @return array<string, callable|array<callable>>
     * @since 1.0.0
     *
     */
    public function getHooks(): array
    {
        return [
            'init' => [
                fn() => app(IncludesManifestBuilder::class)->init(),
            ]
        ];
    }
}
