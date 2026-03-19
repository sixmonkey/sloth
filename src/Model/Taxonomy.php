<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Taxonomy as CorcelTaxonomy;
use PostTypes\Taxonomy as TaxonomyType;
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
                $label = __($label);
            }
        }

        parent::__construct($attributes);
    }

    /**
     * Registers the taxonomy with WordPress.
     *
     * Uses the PostTypes library to register the taxonomy and
     * attach it to the specified post types.
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @uses TaxonomyType For taxonomy registration
     */
    public function register(): void
    {
        if ($this->unique) {
            $this->options['hierarchical'] = false;
            $this->options['parent_item'] = null;
            $this->options['parent_item_colon'] = null;
        }

        $names = array_merge($this->names, ['name' => $this->getTaxonomy()]);
        $options = $this->options;
        $labels = $this->labels;

        $tax = new TaxonomyType($names, $options, $labels);

        foreach ($this->postTypes as $postType) {
            $tax->posttype($postType);
        }

        $tax->register();
        $tax->registerTaxonomy();
        $tax->registerTaxonomyToObjects();
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
     *
     * @uses remove_meta_box() To remove default meta box
     * @uses add_meta_box() To add custom meta box
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
                },
                10,
                2
            );
        }
    }

    /**
     * Renders the taxonomy meta box content.
     *
     * @since 1.0.0
     *
     * @param \WP_Post $wp_post The WordPress post object
     *
     * @return void
     *
     * @uses wp_dropdown_categories() To render the taxonomy dropdown
     */
    public function metabox(\WP_Post $wp_post): void
    {
        $tax = Post::find($wp_post->ID)
            ->taxonomies()
            ->where('taxonomy', '=', $this->taxonomy)
            ->first();

        $args = [
            'taxonomy' => $this->getTaxonomy(),
            'hide_empty' => 0,
            'name' => 'tax_input[' . $this->getTaxonomy() . '][0]',
            'value_field' => 'slug',
            'selected' => $tax->slug ?? '',
        ];

        \wp_dropdown_categories($args);
    }

    /**
     * Gets the taxonomy identifier.
     *
     * @since 1.0.0
     *
     * @return string The taxonomy name
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
     * @return string|\WP_Error The term link URL or WP_Error on failure
     */
    public function getTermLinkAttribute(): string|\WP_Error
    {
        $t = get_term($this->term_id, $this->taxonomy);

        return \get_term_link($t);
    }

    /**
     * Alias for getTermLinkAttribute.
     *
     * @since 1.0.0
     *
     * @return string|\WP_Error The URL attribute value
     */
    public function getUrlAttribute(): string|\WP_Error
    {
        return $this->getTermLinkAttribute();
    }
}
