<?php

namespace Sloth\LayotterBridge\Registrar;

use Illuminate\Support\Str;
use Sloth\LayotterBridge\LayotterElement;
use Sloth\Module\Manifest\ModuleManifestBuilder;
use Sloth\Utility\Utility;

/**
 * Registers Layotter elements discovered by ModuleManifestBuilder.
 *
 * Reads pre-computed module entry data and registers each module that has
 * $layotter defined as a Layotter element. Maintains a mapping between
 * element slugs and their original module classes for runtime resolution.
 *
 * @since 1.0.0
 * @see \Sloth\Module\Manifest\ModuleManifestBuilder For the entry data source
 */
class LayotterElementRegistrar
{
    /**
     * Mapping of element slugs to their module class names.
     *
     * Used at runtime to resolve which module class a Layotter element
     * corresponds to.
     *
     * @var array<string, class-string>
     * @since 1.0.0
     */
    private $elmentModuleMapping = [];

    /**
     * Creates a new LayotterElementRegistrar instance.
     *
     * @param ModuleManifestBuilder $builder The manifest builder that provides
     *                                       the pre-computed entry data.
     * @since 1.0.0
     */
    public function __construct(
        private readonly ModuleManifestBuilder $builder,
    ) {
    }

    /**
     * Register all discovered modules that have Layotter integration.
     *
     * Iterates over the manifest entry data and registers each module with
     * $layotter defined as a Layotter element. The element slug is derived
     * from the module class name (e.g. TeaserModule → teaser-module).
     *
     * @since 1.0.0
     */
    public function registerElements(): void
    {
        collect($this->builder->getEntries())
            ->each(function ($info, $moduleClass) {
                if ($moduleClass::$layotter) {
                    $key = strtolower(substr(strrchr($moduleClass, '\\'), 1));
                    $this->elmentModuleMapping[$key] = $moduleClass;
                    \Layotter::register_element($key, LayotterElement::class);
                }
            });
    }

    /**
     * Resolve a module class name from a Layotter element slug.
     *
     * @param string $key The element slug (e.g. 'teaser-module').
     * @return class-string The resolved module class name, or LayotterElement::class if not found.
     * @since 1.0.0
     */
    public function resolveModuleClass($key)
    {
        return $this->elmentModuleMapping[$key] ?: LayotterElement::class;
    }
}
