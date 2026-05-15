<?php

declare(strict_types=1);
namespace Sloth\Core;

use Override;
use Sloth\Core\Manifest\IncludesManifestBuilder;

/**
 * Service provider for Application-level bootstrapping.
 *
 * Registers the includes manifest builder and loads includes on init.
 *
 * Note: Provider auto-discovery is no longer handled here. The Application
 * itself discovers providers via ProvidersManifestBuilder during
 * Application::registerProviders(). This ensures framework providers register
 * first, followed by discovered app/theme providers.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder For the manifest lifecycle
 * @see IncludesManifestBuilder   For include file discovery
 * @see Application::registerProviders()    For provider auto-discovery
 */
class ApplicationServiceProvider extends ServiceProvider
{
    /**
     * Register the Application service provider.
     *
     * Binds IncludesManifestBuilder as a singleton so its entry data
     * is available during boot.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/app.php', 'app');

        $this->app->singleton(IncludesManifestBuilder::class, fn ($app): IncludesManifestBuilder => new IncludesManifestBuilder($app));
    }

    /**
     * Boot the ApplicationServiceProvider and load the includes manifest.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/app.php' => app()->basePath('config/app.php'),
        ], 'config');

        app(IncludesManifestBuilder::class)->init();
    }
}
