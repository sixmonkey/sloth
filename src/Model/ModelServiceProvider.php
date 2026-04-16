<?php

namespace Sloth\Model;

use Sloth\Core\ServiceProvider;
use Sloth\Model\Registrars\MenuRegistrar;
use Sloth\Model\Registrars\ModelRegistrar;
use Sloth\Model\Registrars\TaxonomyRegistrar;

/**
 * Service provider for model/post type registration and management.
 *
 * Handles:
 * - Navigation menu registration via MenuRegistrar
 * - Taxonomy registration via TaxonomyRegistrar
 * - Custom post type registration via ModelRegistrar
 * - Metabox registration for unique taxonomies
 *
 * @since 1.0.0
 * @see \Sloth\Model\Registrars\MenuRegistrar
 * @see \Sloth\Model\Registrars\TaxonomyRegistrar
 * @see \Sloth\Model\Registrars\ModelRegistrar
 * @see \Sloth\Plugin\Plugin
 */
class ModelServiceProvider extends ServiceProvider
{
    /**
     * Register hooks for model registration.
     *
     * @since 1.0.0
     *
     * @return array<string, callable|array<callable>>
     */
    public function getHooks(): array
    {
        $menuRegistrar = new MenuRegistrar();
        $taxonomyRegistrar = new TaxonomyRegistrar();
        $modelRegistrar = new ModelRegistrar();

        return [
            'init' => [
                fn() => $menuRegistrar->init(),
                fn() => $taxonomyRegistrar->init(),
                fn() => $modelRegistrar->init(),
            ],
            'add_meta_boxes' => [
                fn() => $taxonomyRegistrar->addMetaBoxes(),
            ],
        ];
    }
}
