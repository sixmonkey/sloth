<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\Manifest\IncludesManifestBuilder;
use Sloth\Core\Manifest\ProvidersManifestBuilder;

/**
 * Service provider for Application-level bootstrapping.
 *
 * Registers manifest builders for includes and service providers,
 * and hooks them into WordPress's init action.
 *
 * ## Boot sequence
 *
 * 1. Includes manifest is loaded (scans app/Includes/ and theme/Includes/).
 * 2. Providers manifest is loaded (scans app/Providers/ and theme/Providers/).
 * 3. Discovered providers are registered with the application container.
 *
 * Provider discovery enables theme and app developers to add service providers
 * simply by placing them in the Providers directory — no manual registration needed.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder For the manifest lifecycle
 * @see \Sloth\Core\Manifest\IncludesManifestBuilder   For include file discovery
 * @see \Sloth\Core\Manifest\ProvidersManifestBuilder  For provider class discovery
 */
class ApplicationServiceProvider extends ServiceProvider
{
    /**
     * Register the Application service provider.
     *
     * Binds IncludesManifestBuilder and ProvidersManifestBuilder as singletons
     * so their entry data is available during boot.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(IncludesManifestBuilder::class, fn($app) => new IncludesManifestBuilder($app));
        $this->app->singleton(ProvidersManifestBuilder::class, fn($app) => new ProvidersManifestBuilder($app));
    }

    /**
     * Boot the ApplicationServiceProvider and load all manifests.
     *
     * Loads the includes manifest for automatic file inclusion, then loads
     * the providers manifest and registers each discovered ServiceProvider
     * with the application container.
     *
     * @throws BindingResolutionException
     * @return void
     * @since 1.0.0
     */
    public function boot(): void
    {
        app(IncludesManifestBuilder::class)->init();

        $builder = app(ProvidersManifestBuilder::class);
        $builder->init();

        foreach ($builder->getEntries() as $providerClass) {
            app()->register($providerClass);
        }
    }
}
