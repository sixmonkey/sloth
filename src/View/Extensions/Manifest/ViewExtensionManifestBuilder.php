<?php

declare(strict_types=1);
namespace Sloth\View\Extensions\Manifest;

use Override;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;
use Sloth\View\Extensions\AbstractViewExtension;

/**
 * Builds a manifest for ViewExtension discovery.
 *
 * Scans app/Extensions/View/ and theme/Extensions/View/ for
 * AbstractViewExtension subclasses.
 *
 * @since 1.0.0
 * @see AbstractViewExtension
 */
class ViewExtensionManifestBuilder extends PathBasedManifestBuilder
{
    #[Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(AbstractViewExtension::class);
    }

    #[Override]
    protected function directory(): string
    {
        return 'Extensions/View';
    }
}
