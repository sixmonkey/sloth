<?php

declare(strict_types=1);
namespace Sloth\Module\Manifest;

use Override;
use Sloth\Module\Module;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Support\Manifest\PathBasedManifestBuilder;

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
 * @see PathBasedManifestBuilder For the base class lifecycle
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
     * @return FinderInterface the configured ClassMapFinder
     *
     * @since 1.0.0
     */
    #[Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Module::class);
    }

    /**
     * Return the subdirectory name for Module files.
     *
     * Scans `app/Module/` and `theme/Module/`.
     *
     * @return string always 'Module'
     *
     * @since 1.0.0
     */
    #[Override]
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
     * @param  array<string, string>                                                                $map module class name => absolute file path
     * @return array<string, array{className: string, json: array|bool, jsonParams?: list<string>}>
     *
     * @since 1.0.0
     */
    #[Override]
    protected function entries(array $map): array
    {
        $entries = [];

        /** @var class-string<Module> $moduleClass */
        foreach (array_keys($map) as $moduleClass) {
            $className = substr(strrchr($moduleClass, '\\'), 1);

            $entries[$moduleClass] = [
                'className' => $className,
                'json'      => $moduleClass::$json ?? false,
            ];

            if (is_array($moduleClass::$json) && isset($moduleClass::$json['params'])) {
                $entries[$moduleClass]['jsonParams'] = $moduleClass::$json['params'];
            }
        }

        return $entries;
    }
}
