<?php

declare(strict_types=1);

namespace Sloth\Module\Manifest;

use Sloth\Module\Module;
use Sloth\Support\Manifest\PathBasedManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for module discovery.
 *
 * Scans app/Module/ and theme/Module/ for Module subclasses and writes a
 * manifest that provides pre-computed entry data for JSON/AJAX endpoint
 * registration.
 *
 * ## JSON/AJAX endpoints
 *
 * Modules with `$json` enabled get AJAX handlers and REST routes registered
 * by ModuleRegistrar on the `rest_api_init` hook. The entry data computed
 * here provides the information needed (class name, json config, params).
 *
 * ## Entry data structure
 *
 * ```php
 * [
 *     '\\App\\Module\\TeaserModule' => [
 *         'className'  => 'TeaserModule',
 *         'json'       => ['params' => ['id']],
 *         'jsonParams' => ['id'],
 *     ],
 * ]
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\PathBasedManifestBuilder For the base class lifecycle
 * @see \Sloth\Module\Registrar\ModuleRegistrar           For JSON endpoint registration
 */
class ModuleManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * Return the finder for Module subclass discovery.
     *
     * Uses ClassMapFinder filtered to classes extending Sloth\Module\Module.
     * Non-abstract subclasses are included; abstract base classes are excluded.
     *
     * @return FinderInterface The configured ClassMapFinder.
     * @since 1.0.0
     */
    #[\Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Module::class);
    }

    /**
     * Return the subdirectory name for Module files.
     *
     * Scans `app/Module/` and `theme/Module/`.
     *
     * @return string Always 'Module'.
     * @since 1.0.0
     */
    #[\Override]
    protected function directory(): string
    {
        return 'Module';
    }

    /**
     * Compute entry data for all discovered modules.
     *
     * Iterates over each discovered Module class and extracts the information
     * needed by ModuleRegistrar for JSON/AJAX endpoint registration:
     * - className: the short class name (used for Layotter and route naming)
     * - json: whether the module has JSON endpoints enabled
     * - jsonParams: optional URL parameters for the REST route
     *
     * @param array<string, string> $map Module class name => absolute file path.
     * @return array<string, array{className: string, json: bool|array, jsonParams?: list<string>}>
     * @since 1.0.0
     */
    #[\Override]
    protected function entries(array $map): array
    {
        $entries = [];

        /** @var class-string<Module> $moduleClass */
        foreach ($map as $moduleClass => $file) {
            $className = substr(strrchr($moduleClass, '\\'), 1);

            $entries[$moduleClass] = [
                'className' => $className,
                'json' => $moduleClass::$json ?? false,
            ];

            if (is_array($moduleClass::$json) && isset($moduleClass::$json['params'])) {
                $entries[$moduleClass]['jsonParams'] = $moduleClass::$json['params'];
            }
        }

        return $entries;
    }
}
