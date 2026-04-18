<?php

declare(strict_types=1);

namespace Sloth\Model\Registrars;

use Sloth\Core\Application;
use Sloth\Model\Taxonomy;
use Sloth\Model\Resolvers\TaxonomiesResolver;

/**
 * Registrar for WordPress taxonomy registration.
 *
 * Discovers all Taxonomy subclasses via TaxonomiesResolver and registers
 * them with WordPress.
 *
 * ## Registration flow per taxonomy
 *
 * 1. Skip if $taxonomyClass::$register is false
 * 2. Build labels from $taxonomyClass::$labels or $taxonomyClass::$names
 * 3. Merge labels and $taxonomyClass::$options into registration args
 * 4. Handle unique (single-value) taxonomy settings
 * 5. Register with WordPress
 * 6. Remove default metabox for unique taxonomies
 *
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 */
class TaxonomyRegistrar
{
    /**
     * Registered taxonomies mapping taxonomy slug to class name.
     *
     * @since 1.0.0
     * @var array<string, class-string<Taxonomy>>
     */
    protected array $taxonomies = [];

    /**
     * Constructor.
     *
     * @param Application $app The application container instance.
     * @since 1.0.0
     *
     */
    public function __construct(private Application $app) {}

    /**
     * Discover and register all taxonomies with WordPress.
     *
     * Called on the WordPress 'init' hook via ModelServiceProvider.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $this->registerTaxonomies();
        $this->registerMetaboxes();
    }

    /**
     * Register all discovered taxonomies with WordPress.
     *
     * @since 1.0.0
     */
    protected function registerTaxonomies(): void
    {
        $taxonomies = [];

        /**
         * @param class-string<Taxonomy> $taxonomyClass
         */
        TaxonomiesResolver::resolve()->each(function (string $taxonomyClass) use (&$taxonomies): void {
            if (!$taxonomyClass::$register) {
                return;
            }

            $taxonomy = new $taxonomyClass();

            \register_taxonomy(
                $taxonomy->getTaxonomy(),
                $taxonomyClass::$postTypes,
                $this->buildRegistrationArgs($taxonomyClass, $taxonomy)
            );

            $taxonomies[$taxonomy->getTaxonomy()] = $taxonomyClass;
        });

        $this->taxonomies = $taxonomies;
        $this->app->instance('sloth.taxonomies', $this->taxonomies);
    }

    /**
     * Remove default metaboxes for unique taxonomies.
     *
     * Unique taxonomies replace the default tag-style metabox with a
     * custom radio-button metabox. The default is removed here.
     * The custom one is added via addMetaBoxes() on 'add_meta_boxes'.
     *
     * @since 1.0.0
     */
    protected function registerMetaboxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            if (!$taxonomyClass::$unique) {
                continue;
            }

            $taxonomy = new $taxonomyClass();
            foreach ($taxonomyClass::$postTypes as $postType) {
                \remove_meta_box('tagsdiv-' . $taxonomy->getTaxonomy(), $postType, null);
            }
        }
    }

    /**
     * Add custom metaboxes for unique taxonomies.
     *
     * Called on the 'add_meta_boxes' WordPress hook.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            if (!$taxonomyClass::$unique) {
                continue;
            }

            $taxonomy = new $taxonomyClass();
            $names = $taxonomyClass::$names;
            $singular = $names['singular'] ?? ucfirst($taxonomy->getTaxonomy());

            \add_meta_box(
                'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                $singular,
                $taxonomy->metabox(...),
                $taxonomyClass::$postTypes,
                'side'
            );
        }
    }

    /**
     * Build WordPress taxonomy registration arguments.
     *
     * Merges the taxonomy's $options with generated labels.
     * For unique (single-value) taxonomies, forces hierarchical=false
     * and removes parent item UI elements.
     *
     * @param class-string<Taxonomy> $taxonomyClass The taxonomy class name.
     * @param Taxonomy $taxonomy The taxonomy instance.
     * @return array<string, mixed> Arguments for register_taxonomy().
     * @since 1.0.0
     *
     */
    protected function buildRegistrationArgs(string $taxonomyClass, Taxonomy $taxonomy): array
    {
        $args = $taxonomyClass::$options;
        $args['labels'] = $this->buildLabels($taxonomyClass, $taxonomy);

        if ($taxonomyClass::$unique) {
            $args['hierarchical'] = false;
            $args['parent_item'] = null;
            $args['parent_item_colon'] = null;
        }

        return $args;
    }

    /**
     * Build WordPress taxonomy labels.
     *
     * Only sets labels that contain the taxonomy name — WordPress generates
     * the remaining labels automatically from name and singular_name, correctly
     * translated into the active WordPress language.
     *
     * If $taxonomyClass::$labels is set, those are used directly (with translation).
     * Otherwise labels are auto-generated from $taxonomyClass::$names['singular']
     * and $taxonomyClass::$names['plural'].
     *
     * Falls back to ucfirst($taxonomy->getTaxonomy()) for singular
     * and singular + 's' for plural if $names is not set.
     *
     * @param class-string<Taxonomy> $taxonomyClass The taxonomy class name.
     * @param Taxonomy $taxonomy The taxonomy instance.
     * @return array<string, string> WordPress taxonomy labels.
     * @since 1.0.0
     *
     */
    protected function buildLabels(string $taxonomyClass, Taxonomy $taxonomy): array
    {
        $labels = $taxonomyClass::$labels;
        $names = $taxonomyClass::$names;

        if ($labels !== []) {
            foreach ($labels as $key => $label) {
                if (is_string($label)) {
                    $labels[$key] = __($label);
                }
            }
            return $labels;
        }

        $singular = $names['singular'] ?? ucfirst($taxonomy->getTaxonomy());
        $plural = $names['plural'] ?? $singular . 's';

        return [
            'name' => $plural,
            'singular_name' => $singular,
            'search_items' => sprintf(__('Search %s'), $plural),
            'all_items' => sprintf(__('All %s'), $plural),
            'edit_item' => sprintf(__('Edit %s'), $singular),
            'update_item' => sprintf(__('Update %s'), $singular),
            'add_new_item' => sprintf(__('Add New %s'), $singular),
            'new_item_name' => sprintf(__('New %s Name'), $singular),
            'not_found' => sprintf(__('No %s found'), $plural),
            'menu_name' => $plural,
        ];
    }

    /**
     * Get all registered taxonomies.
     *
     * @return array<string, class-string<Taxonomy>> Taxonomy slug to class name mapping.
     * @since 1.0.0
     *
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }
}
