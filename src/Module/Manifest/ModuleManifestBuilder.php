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
 * manifest that includes all module files on every request.
 *
 * Layotter element classes are generated directly in the manifest — no
 * eval() at runtime. JSON/AJAX endpoints are registered via ModuleJsonEndpointRegistrar
 * on the 'rest_api_init' hook.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ModuleManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Module::class);
    }

    protected function directory(): string
    {
        return 'Module';
    }

    protected function manifestName(): string
    {
        return 'modules.manifest.php';
    }

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
}
