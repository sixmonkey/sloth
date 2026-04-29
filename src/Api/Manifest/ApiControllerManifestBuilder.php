<?php

declare(strict_types=1);

namespace Sloth\Api\Manifest;

use Sloth\Api\Controller;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for WordPress api routes registration.
 *
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ApiControllerManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Controller::class);
    }

    protected function directory(): string
    {
        return 'Api';
    }

    protected function manifestName(): string
    {
        return 'api-controller.manifest.php';
    }

    protected function extraLines(string $identifier, string $file): array
    {
        return [];
    }
}
