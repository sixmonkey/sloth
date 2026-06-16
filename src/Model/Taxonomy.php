<?php

declare(strict_types=1);
namespace Sloth\Model;

use Illuminate\Database\Eloquent\Builder;
use Sloth\Model\Traits\HasACF;
use function admin_url;
use function esc_attr;
use function esc_attr__;
use function esc_html__;
use function esc_html_e;
use function get_term;
use function get_term_link;
use function get_terms;
use function wp_get_object_terms;
use Corcel\Model as CorcelModel;
use Corcel\Model\Meta\TermMeta;
use Corcel\Model\Term;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;
use ReflectionClass;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasMetaFields;
use Walker_Category_Checklist;
use WP_Error;

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
 *
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
 * @see Registrars\TaxonomyRegistrar For taxonomy registration
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

    use HasACF;

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
     *
     * @var string
     */
    protected $table = 'term_taxonomy';

    /**
     * The primary key for the model.
     *
     * @corcel-compat Cannot be typed — Corcel declares $primaryKey without a type.
     *
     * @var string
     */
    protected $primaryKey = 'term_taxonomy_id';

    /**
     * Relationships to eager-load on every query.
     *
     * @corcel-compat Cannot be typed — Corcel declares $with without a type.
     *
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
     *
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
     *
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
     *
     * @var array<string, string>
     */
    public static $labels = [];

    /**
     * Post types that this taxonomy is attached to.
     *
     * @since 1.0.0
     *
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
     *
     * @var bool
     */
    public static $unique = false;

    /**
     * Whether this taxonomy should be registered with WordPress.
     *
     * Set to false to use the taxonomy for querying only.
     *
     * @since 1.0.0
     *
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
     * @param array<string, mixed> $attributes initial attributes
     */
    public function __construct(array $attributes = [])
    {
        if ($this->taxonomy === null) {
            $reflection = new ReflectionClass($this);
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
     * @return array<string> array of post type slugs
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
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'term_id');
    }

    /**
     * Get the parent taxonomy term.
     *
     * @since 1.0.0
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent');
    }

    /**
     * Get child taxonomy terms.
     *
     * @since 1.0.0
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent', 'term_id');
    }

    /**
     * Get all posts associated with this taxonomy term.
     *
     * @since 1.0.0
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(
            Post::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id',
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
     * @return string|WP_Error the term link URL or a WP_Error on failure
     */
    public function getTermLinkAttribute(): string|WP_Error
    {
        $term = get_term($this->term_id, $this->taxonomy);

        if ($term instanceof WP_Error) {
            return $term;
        }

        return get_term_link($term);
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
     * @param  string $key the property name
     * @return mixed
     */
    #[Override]
    public function __get($key)
    {
        $value = parent::__get($key);

        if (!isset($this->$key) && isset($this->term->$key)) {
            return $this->term->$key;
        }

        return $value;
    }

    // -------------------------------------------------------------------------
    // Meta helpers
    // -------------------------------------------------------------------------

    /**
     * Get the meta model class for this taxonomy.
     *
     * @since 1.0.0
     *
     * @return string the fully qualified class name of the meta model
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
     * @return string the foreign key name
     */
    protected function getMetaForeignKey(): string
    {
        return 'term_id';
    }

    /**
     * Get a new query builder filtered by this taxonomy's slug.
     *
     * @return Builder the filtered query builder
     *
     * @since 1.0.0
     */
    #[\Override]
    public function newQuery()
    {
        return isset($this->taxonomy) && $this->taxonomy ?
            parent::newQuery()->where('taxonomy', $this->taxonomy) :
            parent::newQuery();
    }


    /**
     * Get the ACF key for this taxonomy.
     *
     * Returns the WordPress user meta key format: 'term_{id}'.
     *
     * @return string|null The ACF field group key
     */
    public function getAcfKey(): ?string
    {
        return 'term_' . $this->term_id;
    }
}
