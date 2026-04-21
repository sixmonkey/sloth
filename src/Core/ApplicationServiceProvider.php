<?php

namespace Sloth\Core;

use Sloth\Core\Manifest\IncludesManifestBuilder;
use Sloth\Core\ServiceProvider;

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
