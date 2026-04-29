<?php

declare(strict_types=1);

namespace Sloth\Module\Manifest;

use Sloth\Module\Module;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for module discovery and Layotter registration.
 *
 * Scans app/Module/ and theme/Module/ for Module subclasses and writes a
 * manifest that includes all discovered files on every request.
 *
 * ## Layotter integration
 *
 * For modules with `$layotter` defined as an array, the builder generates
 * Layotter element class definitions directly in the manifest. This avoids
 * eval() at runtime — Opcache handles these classes like any other PHP
 * class definition.
 *
 * Example generated code:
 * ```php
 * class TeaserModule extends \Sloth\Module\LayotterElement {
 *     static $module = '\\App\\Module\\TeaserModule';
 * }
 * \Layotter::register_element('teaser-module', 'TeaserModule');
 * ```
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
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder For the base class lifecycle
 * @see \Sloth\Module\Manifest\ModuleRegistrar            For JSON endpoint registration
 */
class ModuleManifestBuilder extends AbstractManifestBuilder
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
     * Return the manifest filename.
     *
     * @return string Always 'modules.manifest.php'.
     * @since 1.0.0
     */
    #[\Override]
    protected function manifestName(): string
    {
        return 'modules.manifest.php';
    }

    /**
     * Generate Layotter element class definitions for compatible modules.
     *
     * For modules that have `$layotter` defined as an array and where the
     * Layotter library is available, this method generates two PHP lines:
     * 1. A class definition extending Sloth\Module\LayotterElement
     * 2. A \Layotter::register_element() call
     *
     * The generated classes are embedded directly in the manifest file,
     * so Opcache caches them like any other class definition. No eval()
     * is needed at runtime.
     *
     * @param string $identifier Fully qualified module class name.
     * @param string $file       Absolute path to the module file.
     * @return list<string>      PHP code lines for Layotter integration.
     * @since 1.0.0
     */
    #[\Override]
    protected function extraLines(string $identifier, string $file): array
    {
        /** @var class-string<Module> $moduleClass */
        $moduleClass = $identifier;

        if (!is_array($moduleClass::$layotter) || !class_exists('\\Layotter')) {
            return [];
        }

        $className      = substr(strrchr($moduleClass, '\\'), 1);
        $elementSlug    = strtolower($className);

        return [
            'class ' . $className . ' extends \\Sloth\\Module\\LayotterElement { static $module = ' . var_export($moduleClass, true) . '; }',
            '\\Layotter::register_element(' . var_export($elementSlug, true) . ', ' . var_export($className, true) . ');',
        ];
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
