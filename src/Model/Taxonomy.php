<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Taxonomy as CorcelTaxonomy;
use Sloth\Model\Post;

/**
 * Taxonomy Model
 *
 * Extends Corcel's Taxonomy model to provide WordPress custom
 * taxonomy registration and management functionality.
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
     * @var bool
     */
    protected bool $unique = false;

    /**
     * The taxonomy identifier.
     *
     * @since 1.0.0
     * @var string|null
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

        if (is_array($this->labels) && count($this->labels) > 0) {
            foreach ($this->labels as &$label) {
                $label = \__($label);
            }
        }

        parent::__construct($attributes);
    }

    /**
     * Registers the taxonomy with WordPress.
     *
     * Uses direct WordPress registration to attach the taxonomy
     * to the specified post types.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $taxonomyName = $this->getTaxonomy();
        if (!$taxonomyName || \taxonomy_exists($taxonomyName)) {
            return;
        }

        $options = $this->options;
        $options['labels'] = $this->labels;

        if ($this->unique) {
            $options['hierarchical'] = false;
            $options['parent_item'] = null;
            $options['parent_item_colon'] = null;
        }

        \register_taxonomy($taxonomyName, null, $options);

        if (!empty($this->postTypes)) {
            foreach ($this->postTypes as $postType) {
                if (\post_type_exists($postType)) {
                    \register_taxonomy_for_object_type($taxonomyName, $postType);
                }
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
     *
     * @return void
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
                        [$me, 'metabox'],
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
     *
     * @return string
     */
    public function getTaxonomy(): string
    {
        return $this->taxonomy ?? '';
    }

    /**
     * Gets the term link URL.
     *
     * @since 1.0.0
     *
     * @return string|\WP_Error
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
     *
     * @return void
     */
    public function metabox($post, $box): void
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
