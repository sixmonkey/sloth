<?php

declare(strict_types=1);
namespace Sloth\Routing\Manifest;

use Sloth\Support\Manifest\FileFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;

/**
 * Builds a manifest for routes discovery.
 *
 * Scans app/routes/ and theme/routes/ for PHP files and writes
 * a manifest that requires them on every request.
 *
 * @since 1.0.0
 * @see PathBasedManifestBuilder
 */
class RoutesManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * The finder to use for discovery.
     *
     * @since 1.0.0
     */
    protected function finder(): FinderInterface
    {
        return new FileFinder();
    }

    /**
     * Subdirectory name to scan, relative to app/ and theme/.
     *
     * @since 1.0.0
     */
    protected function directory(): string
    {
        return 'routes';
    }
}
