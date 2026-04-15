<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Taxonomy as CorcelTaxonomy;
use Sloth\Model\Post;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;

/**
 * Taxonomy Model
 *
 * Extends Corcel's Taxonomy model to provide WordPress custom
 * taxonomy registration and management functionality.
 *
 * This model uses Sloth's own trait implementations for meta fields
 * and aliases, providing full control over attribute resolution.
 *
 * @since 1.0.0
 * @see CorcelTaxonomy For the base Corcel implementation
 *
 * @example
 * ```php
 * class Category extends Taxonomy {
 *     protected array $names = [
 *         'singular' => 'Category',
 *         'plural' => 'Categories',
 *     ];
 *     protected array $postTypes = ['project', 'post'];
 * }
 * ```
 */
class Taxonomy extends CorcelTaxonomy
{
    use HasAliases;
    use HasMetaFields;

    /**
     * Taxonomy names configuration for PostTypes library.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected $names = [];

    /**
     * Taxonomy options for WordPress registration.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected $options = [];

    /**
     * Taxonomy labels for WordPress admin UI.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected $labels = [];

    /**
     * Post types that this taxonomy should be attached to.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected $postTypes = [];

    /**
     * Whether this is a unique (non-hierarchical) taxonomy.
     *
     * @since 1.0.0
     */
    protected bool $unique = false;

    /**
     * The taxonomy identifier.
     *
     * @since 1.0.0
     */
    protected ?string $taxonomy = null;

    /**
     * Creates a new Taxonomy instance.
     *
     * Initializes the taxonomy identifier from the class name
     * if not explicitly set, and processes labels.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $attributes Initial attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->taxonomy === null) {
            $reflection = new \ReflectionClass($this);
            $this->taxonomy = strtolower($reflection->getShortName());
        }

        parent::__construct($attributes);
    }

    /**
     * Get the taxonomy labels.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getLabels(): array
    {
        if ($this->labels !== []) {
            $labels = $this->labels;
            if (is_array($labels) && $labels !== []) {
                foreach ($labels as $key => $label) {
                    if (is_string($label)) {
                        $labels[$key] = \__($label);
                    }
                }
            }

            return $labels;
        }

        $singular = $this->names['singular'] ?? ucfirst((string) $this->taxonomy);
        $plural = $this->names['plural'] ?? $singular . 's';

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
     * Registers the taxonomy with WordPress.
     *
     * Uses direct WordPress registration to attach the taxonomy
     * to the specified post types.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $taxonomyName = $this->getTaxonomy();
        if (!$taxonomyName || \taxonomy_exists($taxonomyName)) {
            return;
        }

        $options = $this->options;
        $options['labels'] = $this->getLabels();

        if ($this->unique) {
            $options['hierarchical'] = false;
            $options['parent_item'] = null;
            $options['parent_item_colon'] = null;
        }

        \register_taxonomy($taxonomyName, null, $options);

        foreach ($this->postTypes as $postType) {
            if (\post_type_exists($postType)) {
                \register_taxonomy_for_object_type($taxonomyName, $postType);
            }
        }
    }

    /**
     * Initializes the taxonomy after WordPress registration.
     *
     * For unique (non-hierarchical) taxonomies, this removes
     * the default meta box and adds a custom one.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        if ($this->unique) {
            $me = $this;

            foreach ($this->postTypes as $post_type) {
                \remove_meta_box('tagsdiv-' . $this->getTaxonomy(), $post_type, null);
            }

            $post_types = $this->postTypes;

            add_action(
                'add_meta_boxes',
                static function () use ($me, $post_types): void {
                    \add_meta_box(
                        'sloth-taxonomy-' . $me->getTaxonomy(),
                        $me->names['singular'],
                        $me->metabox(...),
                        $post_types,
                        'side'
                    );
                }
            );
        }
    }

    /**
     * Gets the taxonomy identifier.
     *
     * @since 1.0.0
     */
    public function getTaxonomy(): string
    {
        return $this->taxonomy ?? '';
    }

    /**
     * Gets the term link URL.
     *
     * @since 1.0.0
     */
    public function getTermLinkAttribute(): string|\WP_Error
    {
        $t = \get_term($this->term_id, $this->taxonomy);
        if ($t instanceof \WP_Error) {
            return $t;
        }

        return \get_term_link($t);
    }

    /**
     * Render the taxonomy metabox.
     *
     * @since 1.0.0
     *
     * @param object $post The post object
     * @param array $box The metabox configuration
     */
    public function metabox($post, array $box): void
    {
        $taxonomy = $this->getTaxonomy();

        echo '<style>#' . $box['id'] . ' .inside { padding: 0; margin: 0; }</style>';

        $terms = \get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms)) {
            echo '<p>' . \esc_html__('An error occurred while retrieving terms.', 'sloth') . '</p>';
            return;
        }

        if (empty($terms)) {
            echo '<p>' . \esc_html__('No terms found.', 'sloth') . '</p>';
            return;
        }

        $post_terms = \wp_get_object_terms($post->ID, $taxonomy, ['fields' => 'ids']);
        $walker = new \Walker_Category_Checklist();

        echo '<ul class="categorychecklist">';
        echo $walker->walk($terms, 0, ['selected' => $post_terms]);
        echo '</ul>';

        echo '<div style="padding: 0 12px 12px;">';
        echo '<p class="description">';
        \esc_html_e('Enter a comma-separated list of new terms or select existing ones above.', 'sloth');
        echo '</p>';
        echo '<p><input type="text" value="" placeholder="' . \esc_attr__('Add new terms', 'sloth') . '" class="widefat" id="sloth-new-terms-' . \esc_attr($taxonomy) . '"></p>';
        echo '<p><button type="button" class="button sloth-add-terms" data-taxonomy="' . \esc_attr($taxonomy) . '">' . \esc_html__('Add', 'sloth') . '</button></p>';
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            $(".sloth-add-terms").on("click", function() {
                var taxonomy = $(this).data("taxonomy");
                var termsInput = $("#sloth-new-terms-" + taxonomy);
                var terms = termsInput.val();
                if (!terms) return;
                
                terms = terms.split(",").map(function(t) { return t.trim(); }).filter(Boolean);
                var currentChecked = [];
                
                $("#' . $box['id'] . ' input[type=checkbox]:checked").each(function() {
                    currentChecked.push($(this).val());
                });
                
                $.ajax({
                    url: "' . \admin_url('admin-ajax.php') . '",
                    type: "POST",
                    data: {
                        action: "sloth_add_terms",
                        taxonomy: taxonomy,
                        terms: terms,
                        post_id: ' . $post->ID . '
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        }
                    }
                });
            });
        });
        </script>';
    }
}
