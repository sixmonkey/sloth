<?php

namespace Sloth\Core\Manifest;

use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\FileFinder;
use Sloth\Support\Manifest\FinderInterface;

class IncludesManifestBuilder extends AbstractManifestBuilder
{

    /**
     * @return FinderInterface
     */
    protected function finder(): FinderInterface
    {
        return new FileFinder();
    }

    /**
     * @return string
     */
    protected function directory(): string
    {
        return 'Includes';
    }

    /**
     * @return string
     */
    protected function manifestName(): string
    {
        return 'includes.manifest.php';
    }
}
