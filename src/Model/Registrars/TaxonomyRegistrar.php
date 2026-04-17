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
 * them with WordPress. Also owns the label generation and registration
 * args building logic that previously lived in Taxonomy.
 *
 * ## Why registration logic lives here
 *
 * Taxonomy is a data object — it should not know about WordPress registration.
 * The Registrar owns the full registration pipeline:
 *
 *   buildLabels() → buildRegistrationArgs() → register_taxonomy()
 *
 * Taxonomy properties ($options, $names, $labels, $postTypes, $unique) are
 * accessed via __get() which falls back to HasLegacyArgs defaults if not
 * declared in the theme taxonomy. The Registrar reads these transparently.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 * @see \Sloth\Model\Traits\HasLegacyArgs
 */
class TaxonomyRegistrar
{
    /**
     * Registered taxonomies mapping taxonomy slug to class name.
     *
     * @since 1.0.0
     * @var array<string, class-string>
     */
    protected array $taxonomies = [];

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

        TaxonomiesResolver::resolve()->each(function (string $taxonomyClass) use (&$taxonomies): void {
            $taxonomy = new $taxonomyClass();

            \register_taxonomy(
                $taxonomy->getTaxonomy(),
                $this->getPostTypes($taxonomy),
                $this->buildRegistrationArgs($taxonomy)
            );

            $taxonomies[$taxonomy->getTaxonomy()] = $taxonomyClass;
        });

        $this->taxonomies = $taxonomies;
        $this->app->instance('sloth.taxonomies', $this->taxonomies);
    }

    /**
     * Remove default metaboxes for unique taxonomies.
     *
     * Unique taxonomies (non-hierarchical, single-value) replace the
     * default tag-style metabox with a custom radio-button metabox.
     * The default metabox is removed here; the custom one is added via
     * addMetaBoxes() on the 'add_meta_boxes' hook.
     *
     * @since 1.0.0
     */
    protected function registerMetaboxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!($taxonomy->unique ?? false)) {
                continue;
            }

            foreach ($this->getPostTypes($taxonomy) as $postType) {
                \remove_meta_box('tagsdiv-' . $taxonomy->getTaxonomy(), $postType, null);
            }
        }
    }

    /**
     * Add custom metaboxes for unique taxonomies.
     *
     * Called on the 'add_meta_boxes' WordPress hook. Adds a custom
     * metabox for each unique taxonomy on each of its post types.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!($taxonomy->unique ?? false)) {
                continue;
            }

            $names = (array)($taxonomy->names ?? []);
            $singular = $names['singular'] ?? ucfirst($taxonomy->getTaxonomy());

            \add_meta_box(
                'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                $singular,
                $taxonomy->metabox(...),
                $this->getPostTypes($taxonomy),
                'side'
            );
        }
    }

    /**
     * Build WordPress taxonomy registration arguments for a taxonomy.
     *
     * Merges the taxonomy's $options (via __get()) with generated labels.
     * For unique (single-value) taxonomies, forces hierarchical=false and
     * removes parent item UI elements.
     *
     * @param Taxonomy $taxonomy The taxonomy instance.
     * @return array<string, mixed> Arguments for register_taxonomy().
     * @since 1.0.0
     *
     */
    protected function buildRegistrationArgs(Taxonomy $taxonomy): array
    {
        $args = (array)($taxonomy->options ?? []);
        $args['labels'] = $this->buildLabels($taxonomy);

        if ($taxonomy->unique ?? false) {
            $args['hierarchical'] = false;
            $args['parent_item'] = null;
            $args['parent_item_colon'] = null;
        }

        return $args;
    }

    /**
     * Build WordPress taxonomy labels for a taxonomy.
     *
     * If the taxonomy declares $labels (via __get() / HasLegacyArgs), those
     * are used directly (with translation). Otherwise labels are auto-generated
     * from $names['singular'] and $names['plural'].
     *
     * Falls back to ucfirst($taxonomy) for singular and singular + 's' for
     * plural if $names is not set.
     *
     * @param Taxonomy $taxonomy The taxonomy instance.
     * @return array<string, string> WordPress taxonomy labels.
     * @since 1.0.0
     *
     */
    protected function buildLabels(Taxonomy $taxonomy): array
    {
        $labels = (array)($taxonomy->labels ?? []);
        $names = (array)($taxonomy->names ?? []);

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
            'name' => __($plural),
            'singular_name' => __($singular),
            'search_items' => sprintf(__('Search %s'), __($plural)),
            'popular_items' => sprintf(__('Popular %s'), __($plural)),
            'all_items' => sprintf(__('All %s'), __($plural)),
            'parent_item' => sprintf(__('Parent %s'), __($singular)),
            'parent_item_colon' => sprintf(__('Parent %s:'), __($singular)),
            'edit_item' => sprintf(__('Edit %s'), __($singular)),
            'view_item' => sprintf(__('View %s'), __($singular)),
            'update_item' => sprintf(__('Update %s'), __($singular)),
            'add_new_item' => sprintf(__('Add New %s'), __($singular)),
            'new_item_name' => sprintf(__('New %s Name'), __($singular)),
            'not_found' => sprintf(__('No %s found'), __($plural)),
            'no_terms' => sprintf(__('No %s'), __($plural)),
            'filter_by_item' => sprintf(__('Filter by %s'), __($singular)),
            'items_list_navigation' => sprintf(__('%s list navigation'), __($plural)),
            'items_list' => sprintf(__('%s list'), __($plural)),
            'back_to_items' => sprintf(__('&larr; Back to %s'), __($plural)),
            'menu_name' => __($plural),
        ];
    }

    /**
     * Get the post types this taxonomy is attached to.
     *
     * Reads $taxonomy->postTypes via __get() which falls back to
     * HasLegacyArgs default ([]) if not declared in the theme taxonomy.
     *
     * @param Taxonomy $taxonomy The taxonomy instance.
     * @return array<string> Post type slugs.
     * @since 1.0.0
     *
     */
    protected function getPostTypes(Taxonomy $taxonomy): array
    {
        return (array)($taxonomy->postTypes ?? []);
    }

    /**
     * Get all registered taxonomies.
     *
     * @return array<string, class-string> Taxonomy slug to class name mapping.
     * @since 1.0.0
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }
}
