<?php

declare(strict_types=1);

namespace Sloth\Model\Registrars;

use Sloth\Core\Application;
use Sloth\Model\Model;
use Sloth\Model\Resolvers\ModelsResolver;

/**
 * Registrar for WordPress post type registration.
 *
 * Discovers all Model subclasses via ModelsResolver and registers them
 * as WordPress post types. Also owns the label generation and registration
 * args building logic that previously lived in Model.
 *
 * ## Why registration logic lives here
 *
 * Model is an Eloquent data object — it should not know about WordPress
 * registration. The Registrar is the only place that calls register_post_type(),
 * so it owns the full registration pipeline:
 *
 *   buildLabels() → buildRegistrationArgs() → unregisterExisting() → register
 *
 * Model properties ($options, $names, $labels, $icon, $layotter) are accessed
 * via __get() which falls back to HasLegacyArgs defaults if not declared in
 * the theme model. The Registrar does not need to know about this — it simply
 * reads $model->options etc. and gets the right value transparently.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Model\Traits\HasLegacyArgs
 */
class ModelRegistrar
{
    /**
     * Registered models mapping post type slug to class name.
     *
     * @since 1.0.0
     * @var array<string, class-string>
     */
    protected array $models = [];

    /**
     * Constructor.
     *
     * @param Application $app The application container instance.
     * @since 1.0.0
     *
     */
    public function __construct(private Application $app)
    {
    }

    /**
     * Discover and register all models with WordPress.
     *
     * Called on the WordPress 'init' hook via ModelServiceProvider.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $this->registerModels();
    }

    /**
     * Register all discovered models as WordPress post types.
     *
     * For each model:
     * 1. Skip if $model->register is false
     * 2. Build registration args (before unregistering — timing matters!)
     * 3. Unregister existing post type if present
     * 4. Register post type with WordPress
     * 5. Register admin column hooks
     * 6. Register model class for newFromBuilder() resolution
     * 7. Configure Layotter integration
     *
     * flush_rewrite_rules() is called once after all models are registered,
     * not per-model, to avoid excessive DB queries.
     *
     * @since 1.0.0
     */
    protected function registerModels(): void
    {
        $models = [];

        ModelsResolver::resolve()->each(function (string $modelName) use (&$models): void {
            $model = new $modelName();

            if (!$model->register) {
                return;
            }

            // Build args BEFORE unregistering — $wp_post_types still has
            // the original WP defaults (e.g. rewrite: false for 'page')
            $args = $this->buildRegistrationArgs($model);
            $this->unregisterExisting($model);

            \register_post_type($model->getPostType(), $args);
            $model->registerColumnHooks();

            $models[$model->getPostType()] = $modelName;
            Model::registerPostType($model->getPostType(), $modelName);

            $this->configureLayotter($model);
        });

        \flush_rewrite_rules();

        $this->models = $models;
        $this->app->instance('sloth.models', $this->models);
    }

    /**
     * Build WordPress post type registration arguments for a model.
     *
     * Merges framework defaults with the model's $options (via __get()),
     * then merges in existing WordPress post type settings if the post
     * type is already registered (e.g. core 'page' post type).
     *
     * The rewrite key is only preserved from $options if explicitly set —
     * otherwise the existing WordPress value wins (e.g. rewrite: false
     * for the core 'page' post type).
     *
     * @param Model $model The model instance.
     * @return array<string, mixed> Arguments for register_post_type().
     * @since 1.0.0
     *
     */
    protected function buildRegistrationArgs(Model $model): array
    {
        $args = array_merge(
            [
                'public' => true,
                'hierarchical' => false,
                'supports' => [
                    'title',
                    'editor',
                    'excerpt',
                    'author',
                    'thumbnail',
                    'revisions',
                    'page-attributes',
                    'post-formats',
                ],
                'menu_position' => 5,
                'show_ui' => true,
            ],
            (array)($model->options ?? [])
        );

        $args['labels'] = $this->buildLabels($model);

        $icon = $model->icon ?? null;
        if ($icon !== null) {
            $args['menu_icon'] = 'dashicons-' . preg_replace('/^dashicons-/', '', (string)$icon);
        }

        if (\post_type_exists($model->getPostType())) {
            $postTypeObject = \get_post_type_object($model->getPostType());
            $args['labels'] = array_merge(
                (array)\get_post_type_labels($postTypeObject),
                $args['labels']
            );

            // If rewrite was not explicitly set in $options, let the
            // existing WP value win (e.g. rewrite: false for 'page')
            if (!isset(($model->options ?? [])['rewrite'])) {
                unset($args['rewrite']);
            }

            global $wp_post_types;
            $args = array_merge((array)$wp_post_types[$model->getPostType()], $args);
        }

        return $args;
    }

    /**
     * Build WordPress post type labels for a model.
     *
     * If the model declares $labels (via __get() / HasLegacyArgs), those
     * are used directly (with translation). Otherwise labels are auto-generated
     * from $names['singular'] and $names['plural'].
     *
     * Falls back to ucfirst($postType) for singular and singular + 's' for plural
     * if $names is not set.
     *
     * @param Model $model The model instance.
     * @return array<string, string> WordPress post type labels.
     * @since 1.0.0
     *
     */
    protected function buildLabels(Model $model): array
    {
        $labels = (array)($model->labels ?? []);
        $names = (array)($model->names ?? []);

        if ($labels !== []) {
            foreach ($labels as $key => $label) {
                if (is_string($label)) {
                    $labels[$key] = __($label);
                }
            }
            return $labels;
        }

        $singular = $names['singular'] ?? ucfirst($model->getPostType());
        $plural = $names['plural'] ?? $singular . 's';

        return [
            'name' => __($plural),
            'singular_name' => __($singular),
            'add_new' => __('Add New'),
            'add_new_item' => sprintf(__('Add New %s'), __($singular)),
            'edit_item' => sprintf(__('Edit %s'), __($singular)),
            'new_item' => sprintf(__('New %s'), __($singular)),
            'view_item' => sprintf(__('View %s'), __($singular)),
            'view_items' => sprintf(__('View %s'), __($plural)),
            'search_items' => sprintf(__('Search %s'), __($plural)),
            'not_found' => sprintf(__('No %s found'), __($plural)),
            'not_found_in_trash' => sprintf(__('No %s found in Trash'), __($plural)),
            'parent_item_colon' => sprintf(__('Parent %s:'), __($singular)),
            'all_items' => sprintf(__('All %s'), __($plural)),
            'archives' => sprintf(__('%s Archives'), __($singular)),
            'attributes' => sprintf(__('%s Attributes'), __($singular)),
            'insert_into_item' => sprintf(__('Insert into %s'), __($singular)),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s'), __($singular)),
            'filter_items_list' => sprintf(__('Filter %s list'), __($plural)),
            'items_list_navigation' => sprintf(__('%s list navigation'), __($plural)),
            'items_list' => sprintf(__('%s list'), __($plural)),
            'menu_name' => __($plural),
            'name_admin_bar' => __($singular),
            'popular_items' => sprintf(__('Popular %s'), __($singular)),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas'), __($plural)),
            'add_or_remove_items' => sprintf(__('Add or remove %s'), __($plural)),
            'choose_from_most_used' => sprintf(__('Choose from the most used %s'), __($plural)),
        ];
    }

    /**
     * Unregister an existing WordPress post type to allow re-registration.
     *
     * Required because WordPress silently ignores register_post_type() calls
     * for already-registered post types. This cleanly removes the existing
     * registration so the model can define the post type with its own settings.
     *
     * Posts of that type are not affected — only the registration metadata
     * is removed.
     *
     * @param Model $model The model instance.
     * @since 1.0.0
     *
     */
    protected function unregisterExisting(Model $model): void
    {
        if (!\post_type_exists($model->getPostType())) {
            return;
        }

        $postTypeObject = \get_post_type_object($model->getPostType());
        $postTypeObject->remove_supports();
        $postTypeObject->remove_rewrite_rules();
        $postTypeObject->unregister_meta_boxes();
        $postTypeObject->remove_hooks();
        $postTypeObject->unregister_taxonomies();

        global $wp_post_types;
        unset($wp_post_types[$model->getPostType()]);

        \do_action('unregistered_post_type', $model->getPostType());
    }

    /**
     * Configure Layotter page builder integration for a model.
     *
     * Reads $model->layotter via __get() which falls back to HasLegacyArgs
     * default (false) if not declared in the theme model.
     *
     * - false   → Layotter disabled for this post type
     * - true    → Layotter enabled with default settings
     * - array   → Layotter enabled with custom settings (e.g. allowed_row_layouts)
     *
     * Skips silently if Layotter is not bound in the container.
     *
     * @param Model $model The model instance.
     * @since 1.0.0
     *
     */
    protected function configureLayotter(Model $model): void
    {
        try {
            $layotter = $model->layotter ?? false;
            $layotterService = $this->app['layotter'];
        } catch (\Throwable) {
            return;
        }

        if ($layotter === false) {
            $layotterService->disable_for_post_type($model->getPostType());
            return;
        }

        $layotterService->enable_for_post_type($model->getPostType());

        if (is_array($layotter) && isset($layotter['allowed_row_layouts'])) {
            $layotterService->set_layouts_for_post_type(
                $model->getPostType(),
                $layotter['allowed_row_layouts']
            );
        }
    }

    /**
     * Get all registered models.
     *
     * @return array<string, class-string> Post type slug to class name mapping.
     * @since 1.0.0
     */
    public function getModels(): array
    {
        return $this->models;
    }
}
