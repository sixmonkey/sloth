<?php

namespace Sloth\Model;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\ServiceProvider;
use Sloth\Model\Manifest\ModelManifestBuilder;
use Sloth\Model\Manifest\TaxonomyManifestBuilder;
use Sloth\Model\Registrars\MenuRegistrar;

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
 * @see \Sloth\Model\Manifest\MenuRegistrar
 * @see \Sloth\Model\Manifest\axonomyRegistrar
 * @see \Sloth\Model\Registrars\ModelRegistrar
 */
class ModelServiceProvider extends ServiceProvider
{
    /**
     * Register the Model service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(MenuRegistrar::class, fn($app) => new MenuRegistrar($app));
        $this->app->singleton(TaxonomyManifestBuilder::class, fn($app) => new TaxonomyManifestBuilder($app));
        $this->app->singleton(ModelManifestBuilder::class, fn($app) => new ModelManifestBuilder($app));
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
     * Fix for hiding columns in wp-admin, which is not supported by johnbillion/extended-cpts
     * @see https://github.com/johnbillion/extended-cpts/
     *
     * @param array $columns
     * @return array
     * @throws BindingResolutionException
     */
    protected function hideAdminColumns(array $columns): array
    {
        $postType = get_current_screen()?->post_type;
        $modelClass = app('sloth.models')[$postType] ?? null;

        if ($modelClass && !empty($modelClass::$admin_columns_hidden)) {
            return array_diff_key($columns, array_flip($modelClass::$admin_columns_hidden));
        }

        return $columns;
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
                fn() => app(TaxonomyManifestBuilder::class)->init(),
                fn() => app(ModelManifestBuilder::class)->init(),
            ],
            'add_meta_boxes' => [
                fn() => app(TaxonomyManifestBuilder::class)->addMetaBoxes(),
            ],
            'registered_post_type' => fn(string $postType) => $this->onPostTypeRegistered($postType),
            'manage_posts_columns' => fn(array $columns) => $this->hideAdminColumns($columns),
        ];
    }
}
