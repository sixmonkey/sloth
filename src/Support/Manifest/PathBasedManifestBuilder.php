<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Base class for manifest builders that discover files from app/ and theme/ directories.
 *
 * Extends AbstractManifestBuilder with path-based discovery. Subclasses only
 * need to implement `directory()` to specify which subdirectory to scan
 * (e.g. `'Model'`, `'Taxonomy'`, `'Api'`).
 *
 * ## What this adds
 *
 * - **directory()** — abstract method that subclasses must implement. Returns
 *   the subdirectory name relative to app/ and theme/.
 * - **directories()** — returns `[app/path(), theme/path()]` based on directory().
 * - **requireFiles** — defaults to true (manifest emits require_once statements).
 *
 * ## Builder hierarchy
 *
 * ```
 * AbstractManifestBuilder (minimal — lifecycle only)
 * ├── PathBasedManifestBuilder (this class — scans app/theme directories)
 * │   ├── ModelManifestBuilder
 * │   ├── TaxonomyManifestBuilder
 * │   ├── ModuleManifestBuilder
 * │   ├── ApiControllerManifestBuilder
 * │   ├── ProvidersManifestBuilder
 * │   └── IncludesManifestBuilder
 * └── VendorProviderManifestBuilder (vendor packages via ComposerFinder)
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder For the base lifecycle
 */
abstract class PathBasedManifestBuilder extends AbstractManifestBuilder
{
    /**
     * Return the subdirectory name to scan, relative to app/ and theme/.
     *
     * Examples: `'Model'`, `'Taxonomy'`, `'Api'`, `'Module'`, `'Includes'`,
     * `'Providers'`. The builder scans both `app/{directory}/` and
     * `theme/{directory}/`.
     *
     * @return string The subdirectory name.
     * @since 1.0.0
     */
    abstract protected function directory(): string;

    /**
     * Directories to scan — app/{directory}/ and theme/{directory}/.
     *
     * @return list<string> Absolute directory paths to scan.
     * @throws BindingResolutionException
     * @since 1.0.0
     */
    #[\Override]
    protected function directories(): array
    {
        return [
            app()->path($this->directory()),
            app()->path($this->directory(), 'theme'),
        ];
    }
}
