<?php

declare(strict_types=1);
namespace Sloth\Core\Manifest;

use Override;
use Sloth\Core\ServiceProvider;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;

/**
 * Builds a manifest for App and Theme ServiceProvider discovery.
 *
 * Scans app/Providers/ and theme/Providers/ for classes extending
 * Sloth\Core\ServiceProvider and writes a manifest that returns
 * a flat array of discovered provider class names.
 *
 * This allows themes and apps to ship their own service providers
 * without manually listing them anywhere — just drop a class in
 * app/Providers/ and it will be discovered automatically.
 *
 * ## Manifest format
 *
 * ```php
 * <?php
 * require_once '/abs/path/MyProvider.php';
 *
 * return [
 *     '\\App\\Providers\\MyProvider',
 *     '\\Theme\\Providers\\CustomProvider',
 * ];
 * ```
 *
 * ## Registration order
 *
 * Providers are registered during Application::registerProviders() which runs
 * on 'after_setup_theme'. Framework providers are registered first, followed
 * by discovered app/theme providers.
 *
 * @since 1.0.0
 * @see PathBasedManifestBuilder   For the base class lifecycle
 * @see \Sloth\Core\Application::registerProviders()    For provider registration
 */
class ProvidersManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * Return the finder for ServiceProvider subclass discovery.
     *
     * Uses ClassMapFinder filtered to classes extending Sloth\Core\ServiceProvider.
     * Non-abstract subclasses are included; abstract base classes are excluded.
     *
     * @return FinderInterface the configured ClassMapFinder
     *
     * @since 1.0.0
     */
    #[Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(ServiceProvider::class);
    }

    /**
     * Return the subdirectory name for Provider files.
     *
     * Scans `app/Providers/` and `theme/Providers/`.
     *
     * @return string always 'Providers'
     *
     * @since 1.0.0
     */
    #[Override]
    protected function directory(): string
    {
        return 'Providers';
    }

    /**
     * Return a flat array of discovered provider class names.
     *
     * The manifest returns `['\\App\\Providers\\MyProvider', ...]` — a simple
     * list of class names. Application::registerProviders() iterates over this
     * array and calls `$this->register()` for each one.
     *
     * @param  array<string, string>               $map provider class name => absolute file path
     * @return list<class-string<ServiceProvider>> flat array of class names
     *
     * @since 1.0.0
     */
    #[Override]
    protected function entries(array $map): array
    {
        return array_keys($map);
    }
}
