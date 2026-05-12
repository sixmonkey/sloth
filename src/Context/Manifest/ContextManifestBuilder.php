<?php

declare(strict_types=1);
namespace Sloth\Context\Manifest;

use Override;
use Sloth\Context\ContextProvider;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;

/**
 * Builds a manifest for context provider discovery.
 *
 * Scans app/Context/ and theme/Context/ for ContextProvider subclasses
 * and writes a manifest that is used to register them automatically.
 *
 * ## Usage
 *
 * Drop a ContextProvider subclass in app/Context/ or theme/Context/:
 *
 * ```php
 * // app/Context/NavigationContextProvider.php
 * class NavigationContextProvider extends ContextProvider
 * {
 *     public function key(): string { return 'navigation'; }
 *     public function resolve(): array { return [...]; }
 * }
 * ```
 *
 * Sloth discovers and registers it automatically on the next request.
 * Run `wp sloth manifest:clear` after deploying new providers.
 *
 * @since 1.0.0
 * @see PathBasedManifestBuilder
 * @see ContextProvider
 */
class ContextManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * Return the finder for ContextProvider subclass discovery.
     *
     * @since 1.0.0
     */
    #[Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(ContextProvider::class);
    }

    /**
     * Return the subdirectory name for context provider files.
     *
     * Scans app/Context/ and theme/Context/.
     *
     * @since 1.0.0
     */
    #[Override]
    protected function directory(): string
    {
        return 'Context';
    }
}
