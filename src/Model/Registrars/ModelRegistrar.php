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
 * as WordPress post types.
 *
 * ## Registration flow per model
 *
 * 1. Skip if $modelClass::$register is false
 * 2. Merge Sloth defaults with model $options
 * 3. For existing post types (e.g. WP Core 'page'): rescue rewrite settings
 *    BEFORE unregistering — otherwise rewrite: false is lost
 * 4. Build labels from $modelClass::$labels or $modelClass::$names
 * 5. Set menu icon from $modelClass::$icon if not null
 * 6. Unregister existing post type (if present)
 * 7. Register with WordPress
 * 8. Register admin column hooks
 * 9. Register model class for newFromBuilder() resolution
 * 10. Configure Layotter
 *
 * flush_rewrite_rules() is called once after all models are registered.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 */
class ModelRegistrar
{
    /**
     * Registered models mapping post type slug to class name.
     *
     * @since 1.0.0
     * @var array<string, class-string<Model>>
     */
    protected array $models = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Application $app The application container instance.
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
     * @since 1.0.0
     */
    protected function registerModels(): void
    {
        $models = [];

        /**
         * @param class-string<Model> $modelClass
         */
        ModelsResolver::resolve()->each(function (string $modelClass) use (&$models): void {
            // 1. Skip if registration is disabled
            if (!$modelClass::$register) {
                return;
            }

            $instance = new $modelClass();
            $postType = $instance->getPostType();

            // 2. Merge Sloth defaults with model options — model wins
            $args = array_merge(
                [
                    'public'        => true,
                    'hierarchical'  => false,
                    'supports'      => [
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
                    'show_ui'       => true,
                ],
                $modelClass::$options
            );

            // 3. Rescue WP defaults BEFORE unregistering.
            // For core post types like 'page', rewrite: false must be
            // read from the existing WP object before we delete it.
            // If rewrite is explicitly set in $options, that wins.
            if (\post_type_exists($postType) && !isset($modelClass::$options['rewrite'])) {
                $args['rewrite'] = \get_post_type_object($postType)->rewrite;
            }

            // 4. Build labels
            $args['labels'] = $this->buildLabels($modelClass, $postType);

            // 5. Menu icon — only set if explicitly defined (not null)
            if ($modelClass::$icon !== null) {
                $args['menu_icon'] = 'dashicons-' . preg_replace('/^dashicons-/', '', $modelClass::$icon);
            }

            // 6. Unregister existing post type
            $this->unregisterExisting($postType);

            // 7. Register with WordPress
            \register_post_type($postType, $args);

            // 8. Admin column hooks
            $instance->registerColumnHooks();

            // 9. Register model class for newFromBuilder() resolution
            $models[$postType] = $modelClass;
            Model::registerPostType($postType, $modelClass);

            // 10. Layotter
            $this->configureLayotter($modelClass, $postType);
        });

        // Flush once after all models — not per model
        \flush_rewrite_rules();

        $this->models = $models;
        $this->app->instance('sloth.models', $this->models);
    }

    /**
     * Build WordPress post type labels for a model.
     *
     * Only sets labels that contain the post type name — WordPress generates
     * the remaining labels automatically from name and singular_name, correctly
     * translated into the active WordPress language.
     *
     * If $modelClass::$labels is set, those are used directly (with translation).
     * Otherwise labels are auto-generated from $modelClass::$names['singular']
     * and $modelClass::$names['plural'].
     *
     * Falls back to ucfirst($postType) for singular and singular + 's' for plural.
     *
     * @since 1.0.0
     *
     * @param class-string<Model> $modelClass The model class name.
     * @param string              $postType   The post type slug.
     * @return array<string, string> WordPress post type labels.
     */
    protected function buildLabels(string $modelClass, string $postType): array
    {
        $labels = $modelClass::$labels;
        $names  = $modelClass::$names;

        if ($labels !== []) {
            foreach ($labels as $key => $label) {
                if (is_string($label)) {
                    $labels[$key] = __($label);
                }
            }
            return $labels;
        }

        $singular = $names['singular'] ?? ucfirst($postType);
        $plural   = $names['plural']   ?? $singular . 's';

        return [
            'name'               => $plural,
            'singular_name'      => $singular,
            'add_new_item'       => sprintf(__('Add New %s'), $singular),
            'edit_item'          => sprintf(__('Edit %s'), $singular),
            'new_item'           => sprintf(__('New %s'), $singular),
            'view_item'          => sprintf(__('View %s'), $singular),
            'search_items'       => sprintf(__('Search %s'), $plural),
            'not_found'          => sprintf(__('No %s found'), $plural),
            'not_found_in_trash' => sprintf(__('No %s found in Trash'), $plural),
            'all_items'          => sprintf(__('All %s'), $plural),
            'menu_name'          => $plural,
            'name_admin_bar'     => $singular,
        ];
    }

    /**
     * Unregister an existing WordPress post type to allow re-registration.
     *
     * WordPress silently ignores register_post_type() calls for already-registered
     * post types. This cleanly removes the existing registration so the model
     * can define the post type with its own settings.
     *
     * Posts of that type are not affected — only the registration metadata
     * is removed.
     *
     * @since 1.0.0
     *
     * @param string $postType The post type slug.
     */
    protected function unregisterExisting(string $postType): void
    {
        if (!\post_type_exists($postType)) {
            return;
        }

        $postTypeObject = \get_post_type_object($postType);
        $postTypeObject->remove_supports();
        $postTypeObject->remove_rewrite_rules();
        $postTypeObject->unregister_meta_boxes();
        $postTypeObject->remove_hooks();
        $postTypeObject->unregister_taxonomies();

        global $wp_post_types;
        unset($wp_post_types[$postType]);

        \do_action('unregistered_post_type', $postType);
    }

    /**
     * Configure Layotter page builder integration for a model.
     *
     * Reads $modelClass::$layotter — falls back to false if not declared.
     *
     * - false → Layotter disabled for this post type
     * - true  → Layotter enabled with default settings
     * - array → Layotter enabled with custom settings (e.g. allowed_row_layouts)
     *
     * Skips silently if Layotter is not bound in the container.
     *
     * @since 1.0.0
     *
     * @param class-string<Model> $modelClass The model class name.
     * @param string              $postType   The post type slug.
     */
    protected function configureLayotter(string $modelClass, string $postType): void
    {
        try {
            $layotter        = $modelClass::$layotter;
            $layotterService = $this->app['layotter'];
        } catch (\Throwable) {
            return;
        }

        if ($layotter === false) {
            $layotterService->disable_for_post_type($postType);
            return;
        }

        $layotterService->enable_for_post_type($postType);

        if (is_array($layotter) && isset($layotter['allowed_row_layouts'])) {
            $layotterService->set_layouts_for_post_type(
                $postType,
                $layotter['allowed_row_layouts']
            );
        }
    }

    /**
     * Get all registered models.
     *
     * @since 1.0.0
     *
     * @return array<string, class-string<Model>> Post type slug to class name mapping.
     */
    public function getModels(): array
    {
        return $this->models;
    }
}
