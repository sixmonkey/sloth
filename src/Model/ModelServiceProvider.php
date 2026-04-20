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
        $this->app->singleton(MenuRegistrar::class, fn($app) => new MenuRegistrar($app));
        $this->app->singleton(TaxonomyRegistrar::class, fn($app) => new TaxonomyRegistrar($app));
        $this->app->singleton(ModelRegistrar::class, fn($app) => new ModelRegistrar($app));
    }

    /**
     * Hook into WordPress' registered_post_type action to bind model classes.
     *
     * Called automatically by WordPress whenever register_post_type() completes.
     * Checks if the registered post type has a corresponding Sloth model and
     * calls Model::registerPostType() to enable newFromBuilder() resolution.
     *
     * This replaces the explicit Model::registerPostType() call in the registrar,
     * making it work for both the manifest fast path and the normal discovery path.
     *
     * @since 1.0.0
     */
    protected function onPostTypeRegistered(string $postType): void
    {
        $models = app()->bound('sloth.models') ? app('sloth.models') : [];

        if (isset($models[$postType])) {
            Model::registerPostType($postType, $models[$postType]);
        }
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
            'registered_post_type' => fn(string $postType) => $this->onPostTypeRegistered($postType),
        ];
    }
}
