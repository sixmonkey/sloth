<?php

declare(strict_types=1);

namespace Sloth\Core\Manifest;

use Sloth\Core\ServiceProvider;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for App and Theme ServiceProvider discovery.
 *
 * Scans app/Providers/ and theme/Providers/ for classes extending
 * Sloth\Core\ServiceProvider and writes a manifest that registers
 * them with the application container on every request.
 *
 * This allows themes and apps to ship their own service providers
 * without manually listing them anywhere — just drop a class in
 * app/Providers/ and it will be discovered automatically.
 *
 * ## Registration order
 *
 * Providers are registered after all framework providers since this
 * builder runs on the 'init' hook. Framework providers run during
 * Application::boot() which fires on 'after_setup_theme'.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ProvidersManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(ServiceProvider::class);
    }

    protected function directory(): string
    {
        return 'Providers';
    }

    protected function manifestName(): string
    {
        return 'providers.manifest.php';
    }

    protected function extraLines(string $identifier, string $file): array
    {
        /** @var class-string<ServiceProvider> $providerClass */
        $providerClass = $identifier;

        return [
            'app()->register(' . var_export($providerClass, true) . ');',
        ];
    }
}
