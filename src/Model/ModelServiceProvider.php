<?php

namespace Sloth\Model;

use Sloth\Core\ServiceProvider;
use Sloth\Model\Registrars\MenuRegistrar;
use Sloth\Model\Registrars\ModelRegistrar;
use Sloth\Model\Registrars\TaxonomyRegistrar;
use Sloth\Module\Module;
use Sloth\Module\Registrars\ModuleRegistrar;

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
     * Register the Module service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(
            'module',
            fn(): Module => new Module()
        );
        $this->app->singleton(MenuRegistrar::class);
        $this->app->singleton(TaxonomyRegistrar::class);
        $this->app->singleton(ModelRegistrar::class);
    }

    /**
     * Register hooks for model registration.
     *
     * @return array<string, callable|array<callable>>
     * @since 1.0.0
     *
     */
    public function getHooks(): array
    {
        return [
            'init' => [
                fn() => app(MenuRegistrar::class)->init(),
                fn() => app(TaxonomyRegistrar::class)->init(),
                fn() => app(ModelRegistrar::class)->init(),
            ],
            'add_meta_boxes' => [
                fn() => app(TaxonomyRegistrar::class)->addMetaBoxes(),
            ],
        ];
    }
}
