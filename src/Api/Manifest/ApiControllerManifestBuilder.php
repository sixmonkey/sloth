<?php

declare(strict_types=1);
namespace Sloth\Api\Manifest;

use Override;
use ReflectionClass;
use Sloth\Api\Controller;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;

/**
 * Builds a manifest for API controller discovery.
 *
 * Scans app/Api/ and theme/Api/ for Controller subclasses and writes a manifest
 * that includes all discovered files and provides route information via its
 * return value.
 *
 * ## Discovery
 *
 * Uses ClassMapFinder to locate all non-abstract classes extending
 * Sloth\Api\Controller. Each discovered class is inspected via Reflection
 * to determine its public methods (potential route handlers).
 *
 * ## Build-time computation
 *
 * The controller's public methods are analyzed once at build time:
 * - Methods starting with `_` are excluded (internal helpers).
 * - The `single` method is excluded (handled separately for special routing).
 * - The `hasSingle` flag indicates whether the controller has a `single()`
 *   method, which affects route generation in ApiServiceProvider.
 *
 * ## Entry data structure
 *
 * ```php
 * [
 *     '\\App\\Api\\NewsController' => [
 *         'routePrefix' => 'news',
 *         'methods'     => ['index', 'featured'],
 *         'hasSingle'   => true,
 *     ],
 * ]
 * ```
 *
 * @since 1.0.0
 * @see PathBasedManifestBuilder For the base class lifecycle
 * @see \Sloth\Api\ApiServiceProvider                   For route registration
 */
class ApiControllerManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * Return the finder for Controller subclass discovery.
     *
     * Uses ClassMapFinder filtered to classes extending Sloth\Api\Controller.
     * Non-abstract subclasses are included; abstract base classes are excluded.
     *
     * @return FinderInterface the configured ClassMapFinder
     *
     * @since 1.0.0
     */
    #[Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Controller::class);
    }

    /**
     * Return the subdirectory name for API controller files.
     *
     * Scans `app/Api/` and `theme/Api/`.
     *
     * @return string always 'Api'
     *
     * @since 1.0.0
     */
    #[Override]
    protected function directory(): string
    {
        return 'Api';
    }

    /**
     * Compute route information for all discovered controllers.
     *
     * Iterates over each discovered Controller class and uses Reflection to
     * determine its public methods. Methods starting with `_` (internal
     * helpers) and the `single` method (handled via special routing) are
     * excluded from the methods list.
     *
     * The route prefix is derived from the class name via Utility::viewize()
     * (e.g. `NewsController` → `news`).
     *
     * @param  array<string, string>                                                             $map controller class name => absolute file path
     * @return array<string, array{routePrefix: string, methods: list<string>, hasSingle: bool}>
     *
     * @since 1.0.0
     */
    #[Override]
    protected function entries(array $map): array
    {
        $entries = [];

        /** @var class-string<Controller> $controllerClass */
        foreach ($map as $controllerClass => $file) {
            $reflection = new ReflectionClass($controllerClass);
            $methods = [];

            foreach ($reflection->getMethods() as $method) {
                if (!str_starts_with($method->name, '_') && $method->name !== 'single') {
                    $methods[] = $method->name;
                }
            }

            $entries[$controllerClass] = [
                'routePrefix' => \Sloth\Utility\Utility::viewize($reflection->getShortName()),
                'methods'     => $methods,
                'hasSingle'   => $reflection->hasMethod('single'),
            ];
        }

        return $entries;
    }
}
