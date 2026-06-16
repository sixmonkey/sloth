<?php

declare(strict_types=1);
namespace Sloth\Model;

use Illuminate\Contracts\Container\BindingResolutionException;
use Override;
use Sloth\Core\ServiceProvider;
use Sloth\Model\Manifest\ModelManifestBuilder;
use Sloth\Model\Manifest\TaxonomyManifestBuilder;
use Sloth\Model\Registrar\ModelRegistrar;
use Sloth\Model\Registrar\TaxonomyRegistrar;
use Sloth\Model\Registrars\MenuRegistrar;

/**
 * Service provider for model/post type registration and management.
 *
 * Coordinates the full lifecycle of WordPress custom post types and taxonomies
 * in the Sloth framework. This includes discovery, registration, container
 * bindings, and admin UI integration.
 *
 * ## Responsibilities
 *
 * - **Discovery**: Delegates to ModelManifestBuilder and TaxonomyManifestBuilder
 *   for scanning app/Model/, theme/Model/, app/Taxonomy/, and theme/Taxonomy/.
 * - **Registration**: Delegates to ModelRegistrar and TaxonomyRegistrar for
 *   calling WordPress registration functions with pre-computed args.
 * - **Container bindings**: Populates `sloth.models` and `sloth.taxonomies`
 *   so other services (e.g. LayotterServiceProvider, onPostTypeRegistered)
 *   can access the discovered classes.
 * - **Metaboxes**: Registers custom radio metaboxes for unique taxonomies
 *   via TaxonomyRegistrar::addMetaBoxes() on the `add_meta_boxes` hook.
 * - **Post type resolution**: Listens to `registered_post_type` to wire up
 *   Model::registerPostType() for newFromBuilder() class resolution.
 * - **Admin columns**: Filters manage_posts_columns to hide columns defined
 *   in $admin_columns_hidden.
 *
 * ## Hook execution order
 *
 * 1. `init` → MenuRegistrar, TaxonomyRegistrar (register + bindings),
 *    ModelRegistrar (register + bindings)
 * 2. `add_meta_boxes` → TaxonomyRegistrar::addMetaBoxes()
 * 3. `registered_post_type` → Model::registerPostType()
 * 4. `manage_posts_columns` → hideAdminColumns()
 *
 * ## Container bindings
 *
 * - **sloth.models**: Maps post type slugs to fully qualified Model class names.
 *   Read by LayotterServiceProvider and onPostTypeRegistered.
 * - **sloth.taxonomies**: Maps taxonomy slugs to fully qualified Taxonomy class
 *   names. Read by TaxonomyRegistrar::addMetaBoxes().
 *
 * @since 1.0.0
 * @see ModelManifestBuilder    For Model discovery
 * @see ModelRegistrar          For post type registration
 * @see TaxonomyManifestBuilder For Taxonomy discovery
 * @see TaxonomyRegistrar       For taxonomy registration
 * @see MenuRegistrar         For menu registration
 */
class ModelServiceProvider extends ServiceProvider
{
    /**
     * Register the Model service provider.
     *
     * Binds manifest builders and registrars as singletons. The registrars
     * receive their builder instance via constructor injection, ensuring
     * they share the same entry data.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(MenuRegistrar::class, fn ($app): MenuRegistrar => new MenuRegistrar($app));
        $this->app->singleton(TaxonomyManifestBuilder::class, fn ($app): TaxonomyManifestBuilder => new TaxonomyManifestBuilder($app));
        $this->app->singleton(TaxonomyRegistrar::class, fn ($app): TaxonomyRegistrar => new TaxonomyRegistrar(app(TaxonomyManifestBuilder::class)));
        $this->app->singleton(ModelManifestBuilder::class, fn ($app): ModelManifestBuilder => new ModelManifestBuilder($app));
        $this->app->singleton(ModelRegistrar::class, fn ($app): ModelRegistrar => new ModelRegistrar(app(ModelManifestBuilder::class)));
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
     * @param string $postType the WordPress post type slug that was registered
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
     * Filter admin post list columns to hide configured columns.
     *
     * Workaround for johnbillion/extended-cpts which doesn't support the
     * $admin_columns_hidden property on models. Reads the property from
     * the model class and removes matching columns from the list table.
     *
     * @param array $columns the current list table columns
     *
     * @throws BindingResolutionException
     *
     * @return array filtered columns with hidden ones removed
     *
     * @since 1.0.0
     * @see https://github.com/johnbillion/extended-cpts/
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
     * Register WordPress action hooks for model and taxonomy management.
     *
     * Returns an array of hook => callback mappings:
     * - **init**: Menu registration, taxonomy + model discovery and registration.
     * - **add_meta_boxes**: Custom radio metaboxes for unique taxonomies.
     * - **registered_post_type**: Post type to model class resolution.
     *
     * @return array<string, array<callable>|callable> hook mappings
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        return [
            'init' => [
                fn () => app(MenuRegistrar::class)->init(),
                $this->initTaxonomies(...),
                $this->initModels(...),
            ],
            'registered_post_type' => $this->onPostTypeRegistered(...),
        ];
    }

    /**
     * Initialize taxonomies: discover, register, and bind to container.
     *
     * Orchestrates the taxonomy lifecycle:
     * 1. Runs TaxonomyManifestBuilder::init() (discovery + manifest loading).
     * 2. Binds sloth.taxonomies to the container (slug => class map).
     * 3. Calls TaxonomyRegistrar::register() to register with WordPress.
     *
     * @since 1.0.0
     */
    protected function initTaxonomies(): void
    {
        $builder = app(TaxonomyManifestBuilder::class);
        $builder->init();

        $entries = $builder->getEntries();

        $this->app->instance('sloth.taxonomies', collect($entries)
            ->mapWithKeys(fn ($entry, $taxonomyClass): array => [new $taxonomyClass()->getTaxonomy() => $taxonomyClass])
            ->all());

        app(TaxonomyRegistrar::class)->register();
    }

    /**
     * Initialize models: discover, register, and bind to container.
     *
     * Orchestrates the model lifecycle:
     * 1. Runs ModelManifestBuilder::init() (discovery + manifest loading).
     * 2. Binds sloth.models to the container (postType => class map).
     * 3. Calls ModelRegistrar::register() to register with WordPress.
     *
     * @since 1.0.0
     */
    protected function initModels(): void
    {
        $builder = app(ModelManifestBuilder::class);
        $builder->init();

        $entries = $builder->getEntries();

        $this->app->instance('sloth.models', collect($entries)
            ->mapWithKeys(fn ($entry, $modelClass): array => [$entry['postType'] => $modelClass])
            ->all());

        app(ModelRegistrar::class)->register();
    }

    /**
     * Register WordPress filter hooks for model management.
     *
     * Returns an array of filter => callback mappings:
     * - **manage_posts_columns**: Hides admin list table columns.
     *
     * @return array<string, array<callable>|callable> filter mappings
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        return [
            'manage_posts_columns' => $this->hideAdminColumns(...),
        ];
    }
}
