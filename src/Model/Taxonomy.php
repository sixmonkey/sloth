<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model as CorcelModel;
use Corcel\Model\Meta\TermMeta;
use Corcel\Model\Term;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;

/**
 * Base Taxonomy class for WordPress custom taxonomies.
 *
 * Extends Corcel\Model directly to provide WordPress taxonomy registration
 * and management. All necessary features are implemented directly without
 * extending Corcel\Model\Taxonomy, ensuring full control over attribute
 * resolution.
 *
 * ## Registration properties
 *
 * Registration-related properties ($names, $options, $labels, $postTypes,
 * $unique, $register) are intentionally untyped static properties.
 * This allows theme developers to override them in child classes without
 * PHP 8.4 typed property inheritance errors. PHPStan is satisfied via
 * @var DocBlocks on each property.
 *
 * The TaxonomyRegistrar reads these via static access: `$taxonomyClass::$names`
 *
 * ## Corcel compatibility
 *
 * Several properties inherited from Corcel\Model cannot be typed because
 * Corcel declares them without types. These are annotated with @var DocBlocks
 * and a @corcel-compat note for clarity.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Registrars\TaxonomyRegistrar For taxonomy registration
 *
 * @example
 * ```php
 * class OrtTaxonomy extends Taxonomy
 * {
 *     protected ?string $taxonomy = 'ort';
 *
 *     public static $names = ['singular' => 'Ort', 'plural' => 'Orte'];
 *     public static $postTypes = ['event'];
 * }
 * ```
 */
class Taxonomy extends CorcelModel
{
    use HasAliases;
    use HasMetaFields;

    // -------------------------------------------------------------------------
    // Corcel-inherited properties — cannot be typed (PHP 8.4 compat)
    // -------------------------------------------------------------------------

    /**
     * Indicates if the model should be timestamped.
     *
     * WordPress taxonomies don't use Laravel timestamps.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The database table used by the model.
     *
     * @corcel-compat Cannot be typed — Corcel declares $table without a type.
     * @var string
     */
    protected $table = 'term_taxonomy';

    /**
     * The primary key for the model.
     *
     * @corcel-compat Cannot be typed — Corcel declares $primaryKey without a type.
     * @var string
     */
    protected $primaryKey = 'term_taxonomy_id';

    /**
     * Relationships to eager-load on every query.
     *
     * @corcel-compat Cannot be typed — Corcel declares $with without a type.
     * @var array<string>
     */
    protected $with = ['term'];

    // -------------------------------------------------------------------------
    // Sloth-specific instance properties
    // -------------------------------------------------------------------------

    /**
     * The WordPress taxonomy identifier.
     *
     * Set automatically from the class name (lowercased) if not explicitly
     * defined in the child class.
     *
     * @since 1.0.0
     * @var string|null
     */
    protected ?string $taxonomy = null;

    // -------------------------------------------------------------------------
    // Registration properties
    //
    // Intentionally untyped static properties. Theme developers override these
    // in child classes without type declarations to avoid PHP 8.4 typed
    // property inheritance errors. PHPStan reads the @var DocBlocks below.
    //
    // The TaxonomyRegistrar reads these via static access: OrtTaxonomy::$names
    // -------------------------------------------------------------------------

    /**
     * Singular and plural display names for label generation.
     *
     * Used by TaxonomyRegistrar::buildLabels() to auto-generate WordPress
     * taxonomy labels when $labels is empty.
     *
     * @since 1.0.0
     * @var array<string, string> e.g. ['singular' => 'Ort', 'plural' => 'Orte']
     */
    public static $names = [];

    /**
     * WordPress taxonomy registration arguments.
     *
     * Merged with WordPress defaults in TaxonomyRegistrar::buildRegistrationArgs().
     * Any valid register_taxonomy() argument can be set here.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    public static $options = [];

    /**
     * WordPress taxonomy labels.
     *
     * When set, these override the auto-generated labels from $names.
     * Supports all WordPress taxonomy label keys.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public static $labels = [];

    /**
     * Post types that this taxonomy is attached to.
     *
     * @since 1.0.0
     * @var array<string> e.g. ['event', 'news']
     */
    public static $postTypes = [];

    /**
     * Whether this is a unique (single-value) taxonomy.
     *
     * When true, the taxonomy behaves like a radio button instead of
     * a checkbox — only one term can be selected per post. The default
     * tag-style metabox is replaced with a custom radio-button metabox.
     *
     * @since 1.0.0
     * @var bool
     */
    public static $unique = false;

    /**
     * Whether this taxonomy should be registered with WordPress.
     *
     * Set to false to use the taxonomy for querying only.
     *
     * @since 1.0.0
     * @var bool
     */
    public static $register = true;

    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    /**
     * Create a new Taxonomy instance.
     *
     * Initializes the taxonomy identifier from the class name (lowercased)
     * if not explicitly set in the child class.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $attributes Initial attributes.
     */
    public function __construct(array $attributes = [])
    {
        if ($this->taxonomy === null) {
            $reflection     = new \ReflectionClass($this);
            $this->taxonomy = strtolower($reflection->getShortName());
        }

        parent::__construct($attributes);
    }

    // -------------------------------------------------------------------------
    // Registration helpers
    // -------------------------------------------------------------------------

    /**
     * Get the WordPress taxonomy identifier.
     *
     * @since 1.0.0
     *
     * @return string The taxonomy slug (e.g. 'category', 'ort').
     */
    public function getTaxonomy(): string
    {
        return $this->taxonomy ?? '';
    }

    /**
     * Get the post types this taxonomy is attached to.
     *
     * @since 1.0.0
     *
     * @return array<string> Array of post type slugs.
     */
    public function getPostTypes(): array
    {
        return static::$postTypes;
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the term relationship.
     *
     * @since 1.0.0
     *
     * @return BelongsTo
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get the parent taxonomy term.
     *
     * @since 1.0.0
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent');
    }

    /**
     * Get child taxonomy terms.
     *
     * @since 1.0.0
     *
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent', 'term_id');
    }

    /**
     * Get all posts associated with this taxonomy term.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany
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

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Get the term link URL.
     *
     * @since 1.0.0
     *
     * @return string|\WP_Error The term link URL or a WP_Error on failure.
     */
    public function getTermLinkAttribute(): string|\WP_Error
    {
        $term = \get_term($this->term_id, $this->taxonomy);

        if ($term instanceof \WP_Error) {
            return $term;
        }

        return \get_term_link($term);
    }

    // -------------------------------------------------------------------------
    // Magic methods
    // -------------------------------------------------------------------------

    /**
     * Handle dynamic property access.
     *
     * Falls back to term attributes (e.g. $taxonomy->name, $taxonomy->slug)
     * when the key is not found on the taxonomy model itself.
     *
     * @since 1.0.0
     *
     * @param string $key The property name.
     * @return mixed
     */
    #[\Override]
    public function __get($key)
    {
        $value = parent::__get($key);

        if (!isset($this->$key) && isset($this->term->$key)) {
            return $this->term->$key;
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Unique taxonomy metabox
    // -------------------------------------------------------------------------

    /**
     * Render the custom metabox for unique (single-value) taxonomies.
     *
     * Displays a checklist of terms with an "add new term" input.
     * Only used when $unique is true — the default tag metabox is replaced
     * with this custom implementation via TaxonomyRegistrar::addMetaBoxes().
     *
     * @since 1.0.0
     *
     * @param object             $post The post object.
     * @param array<string, mixed> $box  The metabox configuration.
     */
    public function metabox(object $post, array $box): void
    {
        $taxonomy = $this->getTaxonomy();

        echo '<style>#' . $box['id'] . ' .inside { padding: 0; margin: 0; }</style>';

        $terms = \get_terms([
            'taxonomy'   => $taxonomy,
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
        $walker     = new \Walker_Category_Checklist();

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

    // -------------------------------------------------------------------------
    // Meta helpers
    // -------------------------------------------------------------------------

    /**
     * Get the meta model class for this taxonomy.
     *
     * @since 1.0.0
     *
     * @return string The fully qualified class name of the meta model.
     */
    protected function getMetaClass(): string
    {
        return TermMeta::class;
    }

    /**
     * Get the foreign key for the meta relationship.
     *
     * @since 1.0.0
     *
     * @return string The foreign key name.
     */
    protected function getMetaForeignKey(): string
    {
        return 'term_id';
    }
}
