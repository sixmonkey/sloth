<?php

declare(strict_types=1);

namespace Sloth\Model;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Corcel\Model\Comment;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Meta\ThumbnailMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Sloth\Field\Image;
use Sloth\Model\Builder\PostBuilder;
use Sloth\Model\Concerns\PostScopes;
use Sloth\Model\Traits\HasACF;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasCustomTimestamps;
use Sloth\Model\Traits\HasMetaFields;
use Sloth\Model\Traits\HasOrderScopes;

/**
 * Base Model class for WordPress post types.
 *
 * Extends Corcel\Model directly to provide a foundation for all custom
 * post types in the Sloth framework. Includes ACF integration, taxonomy
 * relationships, and WordPress-specific query scopes.
 *
 * ## Independence from Corcel
 *
 * This model does NOT extend Corcel\Model\Post or any other Corcel model.
 * All necessary features are implemented directly or via Sloth's own traits.
 * This ensures full control over attribute resolution and prevents issues
 * like infinite recursion in alias handling.
 *
 * ## Registration properties
 *
 * Registration-related properties ($names, $options, $labels, $icon,
 * $register, $layotter) are intentionally untyped static properties.
 * This allows theme developers to override them in child classes without
 * PHP 8.4 typed property inheritance errors. PHPStan is satisfied via
 * @var DocBlocks on each property.
 *
 * The ModelRegistrar reads these via static access: `$modelClass::$names`
 *
 * ## Corcel compatibility
 *
 * Several properties inherited from Corcel\Model cannot be typed because
 * Corcel declares them without types. Typing them in this class would
 * cause PHP 8.4 "must not be defined" errors. These are annotated with
 * `@var` DocBlocks and a `@corcel-compat` note for clarity.
 *
 * @since 1.0.0
 * @see \Corcel\Model For the base Corcel implementation
 * @see \Sloth\Model\Post For the default post model
 * @see \Sloth\Model\Registrars\ModelRegistrar For post type registration
 *
 * @property int $ID           The post ID
 * @property string $post_title   The post title
 * @property string $post_content The post content
 * @property string $post_type    The post type
 * @property string $post_status  The post status
 * @property string $post_name    The post slug
 * @property string $post_excerpt The post excerpt
 */
class Model extends Eloquent
{
    use PostScopes;
    use HasACF;
    use HasAliases;
    use HasCustomTimestamps;
    use HasMetaFields;
    use HasOrderScopes;

    // -------------------------------------------------------------------------
    // Corcel-inherited properties — cannot be typed (PHP 8.4 compat)
    // @corcel-compat: Corcel\Model declares these without types.
    // -------------------------------------------------------------------------

    /**
     * The database table used by the model.
     *
     * @corcel-compat Cannot be typed — Corcel declares $table without a type.
     * @var string
     */
    protected $table = 'posts';

    /**
     * The primary key for the model.
     *
     * @corcel-compat Cannot be typed — Corcel declares $primaryKey without a type.
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * The attributes that should be cast to dates.
     *
     * @corcel-compat Cannot be typed — Corcel declares $dates without a type.
     * @var array<string>
     */
    protected $dates = ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'];

    /**
     * Relationships to eager-load on every query.
     *
     * @corcel-compat Cannot be typed — Corcel declares $with without a type.
     * @var array<string>
     */
    protected $with = [];

    /**
     * The filtered content for this post.
     *
     * @var string|null
     */
    protected static ?string $filedContent = null;

    // -------------------------------------------------------------------------
    // Eloquent-inherited properties — cannot be typed (PHP 8.4 compat)
    // -------------------------------------------------------------------------

    /**
     * Accessors to append to array/JSON representation.
     *
     * @var array<string>
     */
    protected $appends = [
        'title',
        'slug',
        'content',
        'type',
        'mime_type',
        'url',
        'author_id',
        'parent_id',
        'created_at',
        'updated_at',
        'excerpt',
        'status',
        'image',
        'terms',
        'main_category',
        'keywords',
        'keywords_str',
    ];

    /**
     * Default attribute values for new model instances.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'post_content' => '',
        'post_title' => '',
        'post_excerpt' => '',
        'to_ping' => false,
        'pinged' => false,
        'post_content_filtered' => '',
    ];

    /**
     * Attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'post_content',
        'post_title',
        'post_excerpt',
        'post_type',
        'to_ping',
        'pinged',
        'post_content_filtered',
        'post_name',
        'guid',
        'post_parent',
    ];

    // -------------------------------------------------------------------------
    // Sloth-specific instance properties
    // -------------------------------------------------------------------------

    /**
     * Post type identifier for this model.
     *
     * Set automatically from the class name if not explicitly defined.
     * False means this is the base Model class (no specific post type).
     *
     * @since 1.0.0
     * @var string|false
     */
    public static $postType = false;

    /**
     * Post types registered with this model for newFromBuilder() resolution.
     *
     * Maps post type slugs to fully qualified class names so that
     * Eloquent returns the correct model class when querying.
     *
     * @since 1.0.0
     * @var array<string, class-string>
     */
    protected static array $postTypes = [];

    /**
     * Whether the content filter has been applied for this instance.
     *
     * Prevents apply_filters('the_content') from running more than once.
     *
     * @since 1.0.0
     */
    protected bool $filtered = false;


    public static $admin_columns = [];

    public static $admin_columns_hidden = [];

    /**
     * Whether global scopes have been booted for this class.
     *
     * @since 1.0.0
     */
    protected static bool $globalScopesBooted = false;

    /**
     * Aliases for attribute access.
     *
     * Maps alternative property names to their original database columns,
     * allowing $model->title instead of $model->post_title etc.
     *
     * @since 1.0.0
     * @var array<string, string|array<string>>
     */
    protected static array $aliases = [
        'title' => 'post_title',
        'content' => 'post_content',
        'excerpt' => 'post_excerpt',
        'slug' => 'post_name',
        'type' => 'post_type',
        'mime_type' => 'post_mime_type',
        'url' => 'guid',
        'author_id' => 'post_author',
        'parent_id' => 'post_parent',
        'created_at' => 'post_date',
        'updated_at' => 'post_modified',
        'status' => 'post_status',
    ];

    // -------------------------------------------------------------------------
    // Registration properties
    //
    // Intentionally untyped static properties. Theme developers override these
    // in child classes without type declarations to avoid PHP 8.4 typed
    // property inheritance errors. PHPStan reads the @var DocBlocks below.
    //
    // The ModelRegistrar reads these via static access: NewsModel::$names
    // -------------------------------------------------------------------------

    /**
     * Singular and plural display names for label generation.
     *
     * Used by ModelRegistrar::buildLabels() to auto-generate WordPress
     * post type labels when $labels is empty.
     *
     * @since 1.0.0
     * @var array<string, string> e.g. ['singular' => 'News', 'plural' => 'News']
     */
    public static $names = [];

    /**
     * WordPress post type registration arguments.
     *
     * Merged with Sloth defaults in ModelRegistrar::buildRegistrationArgs().
     * Any valid register_post_type() argument can be set here.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    public static $options = [];

    /**
     * WordPress post type labels.
     *
     * When set, these override the auto-generated labels from $names.
     * Supports all WordPress post type label keys.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public static $labels = [];

    /**
     * Dashicon name for the WordPress admin menu icon.
     *
     * Set to a dashicon slug (without the 'dashicons-' prefix) or a full
     * dashicons-* string. Null means WordPress uses its default icon.
     *
     * @since 1.0.0
     * @var string|null e.g. 'news', 'dashicons-megaphone'
     */
    public static $icon = null;

    /**
     * Whether this model should be registered as a WordPress post type.
     *
     * Set to false to use a model for querying only, without registration.
     *
     * @since 1.0.0
     * @var bool
     */
    public static $register = true;

    /**
     * Layotter page builder configuration for this post type.
     *
     * - false         → Layotter disabled
     * - true          → Layotter enabled with default settings
     * - array         → Layotter enabled with custom config
     *                   e.g. ['allowed_row_layouts' => ['full', 'half']]
     *
     * @since 1.0.0
     * @var bool|array<string, mixed>
     */
    public static $layotter = false;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    public const CREATED_AT = 'post_date';
    public const UPDATED_AT = 'post_modified';

    // -------------------------------------------------------------------------
    // Constructor & boot
    // -------------------------------------------------------------------------

    /**
     * Create a new model instance.
     *
     * Initializes the post type from the class name if not explicitly set,
     * sets default attributes including post_type, and boots global scopes.
     *
     * @param array<string, mixed> $attributes Initial model attributes.
     * @since 1.0.0
     *
     */
    public function __construct(array $attributes = [])
    {
        $reflection = new \ReflectionClass($this);

        if ($reflection->getName() === self::class) {
            $this->postType = false;
        }

        if ($this->postType === null) {
            $this->postType = strtolower($reflection->getShortName());
        }

        $this->setRawAttributes(array_merge($this->attributes, [
            'post_type' => $this->getPostType(),
        ]), true);

        parent::__construct($attributes);
        static::bootGlobalScopes();
    }

    /**
     * Boot global query scopes.
     *
     * Registers the 'published_for_guests' scope that filters posts
     * to only show published content to non-logged-in users.
     * Runs once per class to avoid duplicate scope registration.
     *
     * @since 1.0.0
     */
    protected static function bootGlobalScopes(): void
    {
        if (static::$globalScopesBooted) {
            return;
        }

        static::$globalScopesBooted = true;

        static::addGlobalScope('published_for_guests', function (Builder $builder): void {
            if (!is_user_logged_in()) {
                $builder->where('post_status', 'publish');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Eloquent overrides
    // -------------------------------------------------------------------------

    /**
     * Create a model instance from a database row.
     *
     * Returns the correct model class based on the post_type attribute,
     * using the $postTypes registry populated by ModelRegistrar.
     *
     * @param object|array<string, mixed> $attributes The database row attributes.
     * @param null $connection The connection name.
     * @return Model|CorcelModel The model instance.
     * @since 1.0.0
     */
    #[\Override]
    public function newFromBuilder($attributes = [], $connection = null): Model|CorcelModel
    {
        $attributes = (array)$attributes;
        $class = static::class;

        if (isset($attributes['post_type'], static::$postTypes[$attributes['post_type']])) {
            $class = static::$postTypes[$attributes['post_type']];
        }

        /** @var static $model */
        $model = new $class();
        $model->exists = true;
        $model->setRawAttributes($attributes, true);
        $model->setConnection($connection ?: $this->getConnectionName());
        $model->fireModelEvent('retrieved', false);

        if ($this->shouldLoadPreview($model)) {
            $preview = $this->loadPreview($model);
            if ($preview !== null) {
                return $preview;
            }
        }

        return $model;
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param Builder $query The base query builder.
     * @return PostBuilder The custom post builder instance.
     * @since 1.0.0
     *
     */
    #[\Override]
    public function newEloquentBuilder($query): PostBuilder
    {
        return new PostBuilder($query);
    }

    /**
     * Get a new query builder filtered by this model's post type.
     *
     * @return Builder The filtered query builder.
     * @since 1.0.0
     *
     */
    #[\Override]
    public function newQuery()
    {
        return $this->postType
            ? parent::newQuery()->type($this->postType)
            : parent::newQuery();
    }

    // -------------------------------------------------------------------------
    // Post type registration helpers
    // -------------------------------------------------------------------------

    /**
     * Register a post type class for newFromBuilder() resolution.
     *
     * Called by ModelRegistrar after each post type is registered with WordPress.
     *
     * @param string $name The post type slug.
     * @param class-string $class The fully qualified class name.
     * @since 1.0.0
     *
     */
    public static function registerPostType(string $name, string $class): void
    {
        static::$postTypes[$name] = $class;
    }

    /**
     * Clear all registered post types.
     *
     * Primarily used in tests to reset state between test cases.
     *
     * @since 1.0.0
     */
    public static function clearRegisteredPostTypes(): void
    {
        static::$postTypes = [];
    }

    /**
     * Get the post type identifier for this model.
     *
     * @return string The post type slug (e.g. 'post', 'page', 'news').
     * @since 1.0.0
     *
     */
    public function getPostType(): string
    {
        return static::$postType ?: Str::lower((new \ReflectionClass($this))->getShortName());
    }

    // -------------------------------------------------------------------------
    // Preview support
    // -------------------------------------------------------------------------

    /**
     * Check if the preview version of a post should be loaded.
     *
     * Returns true when preview mode is active, the user is logged in,
     * and the post is not a revision or inherited post.
     *
     * @param Model $model The model to check.
     * @return bool True if preview should be loaded.
     * @since 1.0.0
     *
     */
    protected function shouldLoadPreview(self $model): bool
    {
        return isset($_GET['preview'])
            && is_user_logged_in()
            && $model->post_status !== 'inherit'
            && $model->post_type !== 'revision';
    }

    /**
     * Load the latest preview revision for a post.
     *
     * Finds the newest revision authored by the current user.
     *
     * @param Model $model The model to load preview for.
     * @return static|null The preview model or null if not found.
     * @since 1.0.0
     *
     */
    protected function loadPreview(self $model): ?static
    {
        return $model->revision()
            ->where('post_author', get_current_user_id())
            ->newest()
            ->first();
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * Get the post thumbnail meta relationship.
     *
     * @return HasOne
     * @since 1.0.0
     *
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ThumbnailMeta::class, 'post_id')
            ->where('meta_key', '_thumbnail_id');
    }

    /**
     * Get all taxonomies associated with this post.
     *
     * Many-to-many via the term_relationships pivot table.
     *
     * @return BelongsToMany
     * @since 1.0.0
     *
     */
    public function taxonomies(): BelongsToMany
    {
        return $this->belongsToMany(
            Taxonomy::class,
            'term_relationships',
            'object_id',
            'term_taxonomy_id'
        );
    }

    /**
     * Get all comments for this post.
     *
     * @return HasMany
     * @since 1.0.0
     *
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    /**
     * Get the post author.
     *
     * @return BelongsTo
     * @since 1.0.0
     *
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    /**
     * Get the parent post (for hierarchical post types).
     *
     * @return BelongsTo
     * @since 1.0.0
     *
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'post_parent');
    }

    /**
     * Get child posts (for hierarchical post types).
     *
     * @return HasMany
     * @since 1.0.0
     *
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent');
    }

    /**
     * Get media attachments for this post.
     *
     * @return HasMany
     * @since 1.0.0
     *
     */
    public function attachment(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'attachment');
    }

    /**
     * Get post revisions.
     *
     * @return HasMany
     * @since 1.0.0
     *
     */
    public function revision(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'revision');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Get the post content with WordPress filters applied.
     *
     * Applies the_content filter which processes shortcodes, embeds,
     * and paragraph formatting. Runs only once per instance.
     *
     * @return string The filtered HTML content.
     * @since 1.0.0
     *
     */
    public function getContentAttribute(): string
    {
        if ($this->filteredContent === null) {
            $post_content = $this->getAttribute('post_content');
            $this->filteredContent = !is_null($post_content)
                ? \apply_filters('the_content', $post_content)
                : '';
        }

        return $this->filteredContent;
    }

    /**
     * Get the post excerpt with shortcodes stripped.
     *
     * @return string The stripped excerpt.
     * @since 1.0.0
     *
     */
    public function getExcerptAttribute(): string
    {
        return strip_shortcodes($this->post_excerpt ?? '');
    }

    /**
     * Get the permalink for this post.
     *
     * @return bool|string The permalink URL or false on failure.
     * @since 1.0.0
     *
     */
    public function getPermalinkAttribute(): bool|string
    {
        return \get_permalink($this->ID);
    }

    /**
     * Get the featured image thumbnail relationship meta.
     *
     * @return Image The Image object wrapping the thumbnail.
     * @since 1.0.0
     *
     */
    public function getPostThumbnailAttribute(): Image
    {
        return new Image((int)$this->meta->_thumbnail_id);
    }

    /**
     * Get the featured image (alias for getPostThumbnailAttribute).
     *
     * @return Image The Image object wrapping the thumbnail.
     * @since 1.0.0
     *
     */
    public function getImageAttribute(): Image
    {
        return $this->getPostThumbnailAttribute();
    }

    /**
     * Get all terms grouped by taxonomy type.
     *
     * Returns an array keyed by taxonomy name (or 'tag' for post_tag),
     * each containing slug => name pairs.
     *
     * @return array<string, array<string, string>>
     * @since 1.0.0
     *
     */
    public function getTermsAttribute(): array
    {
        return $this->taxonomies
            ->groupBy(fn($taxonomy) => $taxonomy->taxonomy === 'post_tag' ? 'tag' : $taxonomy->taxonomy)
            ->map(fn($group) => $group->mapWithKeys(fn($item) => [$item->term->slug => $item->term->name]))
            ->toArray();
    }

    /**
     * Get the primary category name for this post.
     *
     * Returns the first term name from the first non-empty taxonomy.
     * Falls back to 'Uncategorized' if no terms exist.
     *
     * @return string The main category name.
     * @since 1.0.0
     *
     */
    public function getMainCategoryAttribute(): string
    {
        if (!empty($this->terms)) {
            $taxonomies = array_values($this->terms);
            if (!empty($taxonomies[0])) {
                $terms = array_values($taxonomies[0]);
                return $terms[0];
            }
        }

        return 'Uncategorized';
    }

    /**
     * Get all keywords from all taxonomies as a flat array.
     *
     * @return array<string> All term names.
     * @since 1.0.0
     *
     */
    public function getKeywordsAttribute(): array
    {
        return collect($this->terms)
            ->map(fn($taxonomy) => collect($taxonomy)->values())
            ->collapse()
            ->toArray();
    }

    /**
     * Get all keywords as a comma-separated string.
     *
     * @return string Comma-separated keywords.
     * @since 1.0.0
     *
     */
    public function getKeywordsStrAttribute(): string
    {
        return implode(',', (array)$this->keywords);
    }

    // -------------------------------------------------------------------------
    // Utility methods
    // -------------------------------------------------------------------------

    /**
     * Check if this post has a specific taxonomy term.
     *
     * @param string $taxonomy The taxonomy name (e.g. 'category', 'post_tag').
     * @param string $term The term slug to check for.
     * @return bool True if the post has this term.
     * @since 1.0.0
     *
     */
    public function hasTerm(string $taxonomy, string $term): bool
    {
        return isset($this->terms[$taxonomy][$term]);
    }

    /**
     * Get the post format (e.g. 'standard', 'aside', 'gallery').
     *
     * @return bool|string The post format slug or false if not found.
     * @since 1.0.0
     *
     */
    public function getFormat(): bool|string
    {
        $taxonomy = $this->taxonomies()
            ->where('taxonomy', 'post_format')
            ->first();

        if ($taxonomy && $taxonomy->term) {
            return str_replace('post-format-', '', $taxonomy->term->slug);
        }

        return false;
    }

    /**
     * Get a formatted column value for the admin list view.
     *
     * Returns the value wrapped in a link to the post edit screen.
     *
     * @param string $which The column key (case-insensitive).
     * @return string HTML anchor element linking to the edit screen.
     * @since 1.0.0
     *
     */
    public function getColumn(string $which): string
    {
        $value = $this->{$which} ?? $this->{strtolower($which)};

        return '<a href="' . get_edit_post_link($this->ID) . '">' . $value . '</a>';
    }

    /**
     * Get the ACF field group key for this post.
     *
     * Returns the post ID as a string, which is how ACF identifies
     * field values for post objects.
     *
     * @return string|null The ACF field group key (post ID as string).
     * @since 1.0.0
     *
     */
    public function getAcfKey(): ?string
    {
        return (string)$this->getAttribute('ID');
    }

    // -------------------------------------------------------------------------
    // Magic methods
    // -------------------------------------------------------------------------

    /**
     * Handle dynamic method calls.
     *
     * Intercepts get{Key}Column() calls for admin column rendering,
     * then delegates to Eloquent.
     *
     * @param string $method The method name.
     * @param array<mixed, mixed> $parameters The method arguments.
     * @return mixed
     * @since 1.0.0
     *
     */
    #[\Override]
    public function __call($method, $parameters)
    {
        $parts = preg_split('/(?=[A-Z])/', $method);

        if ($parts[0] === 'get' && ($parts[2] ?? '') === 'Column') {
            return $this->getColumn($parts[1] ?? '');
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Handle dynamic property access.
     *
     * Falls back to WordPress post meta when Eloquent returns null
     * and the key is not a declared property.
     *
     * @param string $key The property name.
     * @return mixed
     * @since 1.0.0
     *
     */
    #[\Override]
    public function __get($key)
    {
        $value = parent::__get($key);

        if ($value === null && !property_exists($this, $key)) {
            return $this->meta->$key;
        }

        return $value;
    }

    /**
     * Handle dynamic property existence checks.
     *
     * Checks Eloquent attributes, casts, loaded relations, ACF field cache,
     * and ACF field definitions.
     *
     * @param string $key The property name.
     * @return bool
     * @since 1.0.0
     *
     */
    #[\Override]
    public function __isset($key): bool
    {
        if (parent::__isset($key)) {
            return true;
        }

        if ($this->hasCast($key)) {
            return true;
        }

        if ($this->relationLoaded('meta') && $this->meta->contains('meta_key', $key)) {
            return true;
        }

        if (
            method_exists($this, 'getAcfKey')
            && isset(HasACF::$acfFieldCache[$this->getAcfKey()])
            && HasACF::$acfFieldCache[$this->getAcfKey()]->has($key)
        ) {
            return true;
        }

        if (function_exists('acf_maybe_get_field') && method_exists($this, 'getAcfKey')) {
            return acf_maybe_get_field($key, $this->getAcfKey()) !== false;
        }

        return false;
    }

    /**
     * Convert the model to an array.
     *
     * Extends Eloquent's toArray() to include mutated attributes
     * that are not already present in the array.
     *
     * @return array<string, mixed>
     * @since 1.0.0
     *
     */
    #[\Override]
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->getMutatedAttributes() as $key) {
            if (!array_key_exists($key, $array)) {
                $array[$key] = $this->{$key};
            }
        }

        if (is_array($this->hidden)) {
            foreach ($this->hidden as $k) {
                unset($array[$k]);
            }
        }

        return $array;
    }

    // -------------------------------------------------------------------------
    // Meta helpers
    // -------------------------------------------------------------------------

    /**
     * Get the meta model class for this model.
     *
     * @return string The fully qualified class name of the meta model.
     * @since 1.0.0
     *
     */
    protected function getMetaClass(): string
    {
        return PostMeta::class;
    }

    /**
     * Get the foreign key for the meta relationship.
     *
     * @return string The foreign key name.
     * @since 1.0.0
     *
     */
    protected function getMetaForeignKey(): string
    {
        return 'post_id';
    }
}
