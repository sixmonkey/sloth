<?php

declare(strict_types=1);

namespace Sloth\Core\Manifest;

use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\FileFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for includes discovery.
 *
 * Scans app/Includes/ and theme/Includes/ for PHP files and writes
 * a manifest that requires them on every request.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class IncludesManifestBuilder extends AbstractManifestBuilder
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
        return 'Includes';
    }

    /**
     * Filename for the generated manifest in the cache directory.
     *
     * @since 1.0.0
     */
    protected function manifestName(): string
    {
        return 'includes.manifest.php';
    }
}
