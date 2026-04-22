<?php

declare(strict_types=1);

namespace Sloth\Api\Manifest;

use Sloth\Api\Controller;
use Sloth\Model\Model;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Utility\Utility;

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
        return [
        ];
    }

    protected function bindings(array $map): array
    {
        return [
            'sloth.api-controllers' => collect($map)
                ->mapWithKeys(function ($file, $controllerClass) {
                    /** @var class-string<Controller> $controllerClass */
                    return [Utility::viewize((new \ReflectionClass($controllerClass))->getShortName()) => $controllerClass];
                })
                ->all(),
        ];
    }
}
