<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model as CorcelModel;
use Corcel\Model\Meta\TermMeta;
use Corcel\Model\Term;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;

/**
 * Taxonomy Model
 *
 * Extends Corcel\Model directly to provide WordPress custom
 * taxonomy registration and management functionality.
 *
 * ## Independence from Corcel
 *
 * This model does NOT extend Corcel\Model\Taxonomy. Instead, it implements
 * all necessary features directly, ensuring full control over attribute resolution.
 *
 * This model uses Sloth's own trait implementations for meta fields
 * and aliases.
 *
 * @since 1.0.0
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
class Taxonomy extends CorcelModel
{
    use HasAliases;
    use HasMetaFields;

    /**
     * Indicates if the model should be timestamped.
     *
     * WordPress taxonomies don't use Laravel timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'term_taxonomy';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'term_taxonomy_id';

    /**
     * The relationships to eager-load on every query.
     *
     * @var array<string>
     */
    protected $with = ['term'];

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
     * Get the term relationship.
     *
     * @return BelongsTo The term relationship
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get the parent taxonomy.
     *
     * @return BelongsTo The parent relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent');
    }

    /**
     * Get child taxonomy terms.
     *
     * Returns all taxonomy terms that have this term as their parent.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany The child terms relationship
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent', 'term_id');
    }

    /**
     * Get all posts associated with this taxonomy term.
     *
     * @return BelongsToMany The posts relationship
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id'
        );
    }

    /**
     * Get the term link URL.
     *
     * @return string|\WP_Error The term link URL or error
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
     * Magic method to return term attributes directly.
     *
     * Provides access to term attributes as if they were taxonomy attributes.
     *
     * @param string $key The attribute key
     *
     * @return mixed The attribute value
     */
    public function __get($key)
    {
        if (!isset($this->$key) && isset($this->term->$key)) {
            return $this->term->$key;
        }

        return parent::__get($key);
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
     * Get registration arguments for WordPress taxonomy registration.
     *
     * Builds and returns the arguments array required by WordPress's
     * register_taxonomy() function. This includes:
     * - Labels generated from $this->names via getLabels()
     * - Options from $this->options
     * - Special handling for unique (non-hierarchical) taxonomies
     *
     * ## Unique Taxonomies
     *
     * If $this->unique is true, the taxonomy is treated as non-hierarchical
     * (like tags). This sets hierarchical=false, parent_item=null, and
     * parent_item_colon=null to remove parent-related UI elements.
     *
     * ## Usage
     *
     * Called by Plugin::loadTaxonomies():
     * ```php
     * register_taxonomy(
     *     $taxonomy->getTaxonomy(),
     *     $taxonomy->getPostTypes(),
     *     $taxonomy->getRegistrationArgs()
     * );
     * ```
     *
     * @since 1.0.0
     * @see getLabels() For label generation
     * @see getPostTypes() For attached post types
     * @see \register_taxonomy() WordPress function
     *
     * @return array<string, mixed> Arguments for register_taxonomy()
     */
    public function getRegistrationArgs(): array
    {
        $options = $this->options;
        $options['labels'] = $this->getLabels();

        if ($this->unique) {
            $options['hierarchical'] = false;
            $options['parent_item'] = null;
            $options['parent_item_colon'] = null;
        }

        return $options;
    }

    /**
     * Get post types that this taxonomy is attached to.
     *
     * Returns the array of post type slugs that this taxonomy should
     * be associated with. When registering the taxonomy with WordPress,
     * these post types will be able to use this taxonomy for organizing
     * their content.
     *
     * ## Example
     *
     * ```php
     * class Category extends Taxonomy
     * {
     *     protected array $postTypes = ['post', 'project'];
     * }
     *
     * // Returns ['post', 'project']
     * $category->getPostTypes();
     * ```
     *
     * @since 1.0.0
     * @see getRegistrationArgs() For full taxonomy registration
     * @see \register_taxonomy_for_object_type() WordPress function (used internally)
     *
     * @return array<string> Array of post type slugs (e.g., ['post', 'page'])
     */
    public function getPostTypes(): array
    {
        return $this->postTypes;
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

    /**
     * Get the meta class for this model.
     *
     * @return string The fully qualified class name of the meta model
     */
    protected function getMetaClass(): string
    {
        return TermMeta::class;
    }

    /**
     * Get the foreign key for the meta relationship.
     *
     * @return string The foreign key name
     */
    protected function getMetaForeignKey(): string
    {
        return 'term_id';
    }
}
