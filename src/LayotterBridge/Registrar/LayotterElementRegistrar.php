<?php

namespace Sloth\LayotterBridge\Registrar;

use Illuminate\Support\Str;
use Sloth\LayotterBridge\LayotterElement;
use Sloth\Module\Manifest\ModuleManifestBuilder;
use Sloth\Utility\Utility;

class LayotterElementRegistrar
{
    private $elmentModuleMapping = [];

    /**
     * Creates a new ModuleRegistrar instance.
     *
     * @param ModuleManifestBuilder $builder The manifest builder that provides
     *                                       the pre-computed entry data.
     * @since 1.0.0
     */
    public function __construct(
        private readonly ModuleManifestBuilder $builder,
    ) {
    }

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

    public function resolveModuleClass($key)
    {
        return $this->elmentModuleMapping[$key] ?: LayotterElement::class;
    }
}
