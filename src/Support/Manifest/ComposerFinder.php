<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Illuminate\Support\Collection;
use Illuminate\Support\Env;
use Sloth\Core\Application;

/**
 * Discovers service providers from installed Composer packages.
 *
 * Parses vendor/composer/installed.json to find packages that declare
 * Sloth service providers in their extra.folivoro.providers configuration.
 *
 * ## Composer.json format (vendor package)
 *
 * ```json
 * {
 *     "name": "vendor/package",
 *     "extra": {
 *         "folivoro": {
 *             "providers": [
 *                 "Vendor\\Package\\ServiceProvider"
 *             ]
 *         }
 *     }
 * }
 * ```
 *
 * ## Composer.json format (app/theme — to ignore packages)
 *
 * ```json
 * {
 *     "extra": {
 *         "folivoro": {
 *             "dont-discover": [
 *                 "vendor/package"
 *             ]
 *         }
 *     }
 * }
 * ```
 *
 * ## Finder output
 *
 * Returns a map of `[provider-class => package-name]` for all discovered
 * providers. The paths in the map are empty strings since Composer handles
 * autoloading — no require_once is needed.
 *
 * @since 1.0.0
 * @see \Sloth\Core\Manifest\VendorProviderManifestBuilder For the builder that uses this finder
 */
class ComposerFinder implements FinderInterface
{
    /**
     * Creates a new ComposerFinder instance.
     *
     * @param Application $app The application container, used for path resolution.
     * @since 1.0.0
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Scan installed Composer packages and return discovered providers.
     *
     * The $paths parameter is ignored — this finder reads from installed.json
     * regardless of which directories are passed. The interface requires the
     * parameter but it exists for consistency with other finder implementations.
     *
     * @param list<string> $paths Ignored. Composer packages are discovered from installed.json.
     * @return array<string, string> Map of provider-class => package-name.
     * @since 1.0.0
     */
    public function find(array $paths): array
    {
        $packages = $this->getInstalledPackages();
        $ignored = $this->getIgnoredPackages();

        return (new Collection($packages))
            ->mapWithKeys(function ($package) {
                $providers = $package['extra']['folivoro']['providers'] ?? [];
                return [$package['name'] => $providers];
            })
            ->reject(function ($providers, $package) use ($ignored) {
                return in_array($package, $ignored) || empty($providers);
            })
            ->flatMap(function ($providers, $package) {
                $result = [];
                foreach ($providers as $provider) {
                    $result[$provider] = $package;
                }
                return $result;
            })
            ->all();
    }

    /**
     * Get all installed packages from composer/installed.json.
     *
     * @return array<array{name: string, extra?: array}> List of installed package metadata.
     * @since 1.0.0
     */
    protected function getInstalledPackages(): array
    {
        $vendorPath = Env::get('COMPOSER_VENDOR_DIR') ?: $this->app->basePath('vendor');
        $path = $vendorPath . '/composer/installed.json';

        if (!is_file($path)) {
            return [];
        }

        $installed = json_decode(file_get_contents($path), true);

        return $installed['packages'] ?? $installed ?? [];
    }

    /**
     * Get packages that should be ignored from the app's composer.json.
     *
     * Reads the extra.folivoro.dont-discover key from the application's
     * root composer.json.
     *
     * @return list<string> Package names to ignore.
     * @since 1.0.0
     */
    protected function getIgnoredPackages(): array
    {
        $composerPath = $this->app->basePath('composer.json');

        if (!is_file($composerPath)) {
            return [];
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        return $composer['extra']['folivoro']['dont-discover'] ?? [];
    }
}
