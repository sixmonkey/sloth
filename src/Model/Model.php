<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Concerns\CustomTimestamps;
use Corcel\Concerns\MetaFields;
use Corcel\Concerns\OrderScopes;
use Corcel\Model as CorcelModel;
use Corcel\Model\Comment;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Meta\ThumbnailMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PostTypes\PostType;
use Sloth\Field\Image;
use Sloth\Model\Builder\PostBuilder;
use Sloth\Model\Traits\HasACF;

/**
 * Base Model class for WordPress post types.
 *
 * This class extends Corcel\Model to provide a foundation for all custom
 * post types in the Sloth framework. It includes ACF integration, taxonomy
 * relationships, and WordPress-specific query scopes.
 *
 * @since 1.0.0
 * @see \Corcel\Model For the base Corcel implementation
 * @see \Sloth\Model\Post For the default post model
 *
 * @property int $ID The post ID
 * @property string $post_title The post title
 * @property string $post_content The post content
 * @property string $post_type The post type
 * @property string $post_status The post status
 */
class Model extends CorcelModel
{
    use HasACF;
    use MetaFields;
    use OrderScopes;
    use CustomTimestamps;

    public const CREATED_AT = 'post_date';

    public const UPDATED_AT = 'post_modified';

    protected $table = 'posts';

    protected $primaryKey = 'ID';

    protected $dates = ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'];

    protected $with = ['meta'];

    protected array $names = [];

    protected array $options = [];

    protected array $labels = [];

    public static array $layotter = [];

    public bool $register = true;

    public string $post_content = ' ';

    protected $icon;

    protected bool $filtered = false;

    public array $admin_columns = [];

    public array $admin_columns_hidden = [];

    protected static bool $globalScopesBooted = false;

    /**
     * Default attribute values for new model instances.
     *
     * @since 1.0.0
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
     * @since 1.0.0
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

    /**
     * Create a new model instance.
     *
     * Initializes the post type from the class name if not set,
     * translates labels via __(), sets default attributes including
     * the post_type, and boots global scopes.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $attributes Initial model attributes
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

        if (is_array($this->labels) && count($this->labels)) {
            foreach ($this->labels as &$label) {
                $label = __($label);
            }
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

    /**
     * Create a new Eloquent query builder for the model.
     *
     * Uses Sloth's custom PostBuilder which adds WordPress-specific
     * query methods like whereStatus(), withArchives(), etc.
     *
     * @since 1.0.0
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The base query builder
     * @return PostBuilder The custom post builder instance
     * @see \Sloth\Model\Builder\PostBuilder
     */
    #[\Override]
    public function newEloquentBuilder($query): PostBuilder
    {
        return new PostBuilder($query);
    }

    /**
     * Get a new query builder for the model's table.
     *
     * Filters by the model's post type if one is set. This ensures
     * queries only return posts of the specific custom post type.
     *
     * @since 1.0.0
     *
     * @return Builder The filtered query builder
     */
    #[\Override]
    public function newQuery()
    {
        return $this->postType
            ? parent::newQuery()->type($this->postType)
            : parent::newQuery();
    }

    /**
     * Get the post thumbnail relationship.
     *
     * @since 1.0.0
     *
     * @return HasOne The thumbnail meta relationship
     * @see \Corcel\Model\Meta\ThumbnailMeta
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ThumbnailMeta::class, 'post_id')
            ->where('meta_key', '_thumbnail_id');
    }

    /**
     * Get all taxonomies associated with this post.
     *
     * Many-to-many relationship via term_relationships table.
     *
     * @since 1.0.0
     *
     * @return BelongsToMany The taxonomies relationship
     * @see \Sloth\Model\Taxonomy
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
     * @since 1.0.0
     *
     * @return HasMany The comments relationship
     * @see \Corcel\Model\Comment
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    /**
     * Get the post author.
     *
     * @since 1.0.0
     *
     * @return BelongsTo The author relationship
     * @see \Sloth\Model\User
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    /**
     * Get the parent post if this is a hierarchical post type.
     *
     * @since 1.0.0
     *
     * @return BelongsTo The parent post relationship
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'post_parent');
    }

    /**
     * Get child posts (for hierarchical post types like pages).
     *
     * @since 1.0.0
     *
     * @return HasMany The children relationship
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent');
    }

    /**
     * Get media attachments for this post.
     *
     * Filters to only return posts with post_type 'attachment'.
     *
     * @since 1.0.0
     *
     * @return HasMany The attachments relationship
     */
    public function attachment(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'attachment');
    }

    /**
     * Get post revisions.
     *
     * Filters to only return posts with post_type 'revision'.
     *
     * @since 1.0.0
     *
     * @return HasMany The revisions relationship
     */
    public function revision(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'revision');
    }

    /**
     * Get the post content with WordPress filters applied.
     *
     * Applies the_content filter which handles shortcodes, embeds,
     * and paragraph formatting. Result is cached to avoid re-processing.
     *
     * @since 1.0.0
     *
     * @return string The filtered HTML content
     */
    public function getContentAttribute(): string
    {
        if (!$this->filtered) {
            $post_content = $this->getAttribute('post_content');
            if (!is_null($post_content)) {
                $this->post_content = \apply_filters('the_content', $post_content);
            }

            $this->filtered = true;
        }

        return (string) $this->post_content;
    }

    /**
     * Get the permalink for this post.
     *
     * @since 1.0.0
     *
     * @return bool|string The permalink URL or false on failure
     */
    public function getPermalinkAttribute(): bool|string
    {
        return \get_permalink($this->ID);
    }

    /**
     * Get the featured image/thumbnail for this post.
     *
     * @since 1.0.0
     *
     * @return Image The Image object wrapping the thumbnail
     * @see \Sloth\Field\Image
     */
    public function getPostThumbnailAttribute(): Image
    {
        return new Image((int) $this->meta->_thumbnail_id);
    }

    /**
     * Get the featured image (alias for getPostThumbnailAttribute).
     *
     * @since 1.0.0
     *
     * @return Image The Image object wrapping the thumbnail
     */
    public function getImageAttribute(): Image
    {
        return $this->getPostThumbnailAttribute();
    }

    /**
     * Get all terms (taxonomies) grouped by taxonomy type.
     *
     * Returns an array grouped by taxonomy name (or 'tag' for post_tag),
     * with each group containing slug => name pairs.
     *
     * @since 1.0.0
     *
     * @return array<string, array<string, string>> Grouped terms by taxonomy
     * @see taxonomies() For the relationship used
     */
    public function getTermsAttribute(): array
    {
        return $this->taxonomies->groupBy(fn(
            $taxonomy
        ) => $taxonomy->taxonomy === 'post_tag' ? 'tag' : $taxonomy->taxonomy)->map(fn(
            $group
        ) => $group->mapWithKeys(fn($item) => [$item->term->slug => $item->term->name]))->toArray();
    }

    /**
     * Get the primary/first category for this post.
     *
     * Returns the first term name from the first non-empty taxonomy.
     * Falls back to 'Uncategorized' if no terms exist.
     *
     * @since 1.0.0
     *
     * @return string The main category name
     * @see getTermsAttribute() For the terms data source
     */
    public function getMainCategoryAttribute(): string
    {
        $mainCategory = 'Uncategorized';

        if (!empty($this->terms)) {
            $taxonomies = array_values($this->terms);
            if (!empty($taxonomies[0])) {
                $terms = array_values($taxonomies[0]);
                $mainCategory = $terms[0];
            }
        }

        return $mainCategory;
    }

    /**
     * Get all keywords from all taxonomies as a flat array.
     *
     * Collapses all terms from all taxonomies into a single array.
     *
     * @since 1.0.0
     *
     * @return array<string> All term names as flat array
     * @see getTermsAttribute() For the source data
     */
    public function getKeywordsAttribute(): array
    {
        return collect($this->terms)->map(fn($taxonomy) => collect($taxonomy)->values())->collapse()->toArray();
    }

    /**
     * Get all keywords as a comma-separated string.
     *
     * @since 1.0.0
     *
     * @return string Comma-separated keyword string
     * @see getKeywordsAttribute() For the source data
     */
    public function getKeywordsStrAttribute(): string
    {
        return implode(',', (array) $this->keywords);
    }

    /**
     * Get the post type identifier.
     *
     * @since 1.0.0
     *
     * @return string The post type (e.g., 'post', 'page', or custom post type)
     */
    public function getPostType(): string
    {
        return (string) $this->postType;
    }

    /**
     * Check if this post has a specific term.
     *
     * @since 1.0.0
     *
     * @param string $taxonomy The taxonomy name (e.g., 'category', 'post_tag')
     * @param string $term The term slug to check for
     * @return bool True if the post has this term
     * @see getTermsAttribute() For the terms data
     */
    public function hasTerm(string $taxonomy, string $term): bool
    {
        return isset($this->terms[$taxonomy][$term]);
    }

    /**
     * Get the post format (e.g., 'standard', 'aside', 'gallery').
     *
     * Looks up the post_format taxonomy for this post.
     *
     * @since 1.0.0
     *
     * @return bool|string The post format slug or false if not found
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
     * Create a model instance from a database row.
     *
     * Handles preview/draft loading - if the fetched post is a preview
     * and a published version exists, returns that instead.
     *
     * @since 1.0.0
     *
     * @param object $attributes The database row attributes
     * @param string|null $connection The connection name
     * @return static The model instance
     */
    #[\Override]
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        /** @var static $model */
        $model = parent::newFromBuilder($attributes, $connection);

        if ($this->shouldLoadPreview($model)) {
            $preview = $this->loadPreview($model);
            if ($preview !== null) {
                return $preview;
            }
        }

        return $model;
    }

    /**
     * Check if we should load the preview version of a post.
     *
     * Returns true when preview mode is active, user is logged in,
     * and it's not a revision or inherited post.
     *
     * @since 1.0.0
     *
     * @param Model $model The model to check
     * @return bool True if preview should be loaded
     */
    protected function shouldLoadPreview(self $model): bool
    {
        return isset($_GET['preview'])
            && is_user_logged_in()
            && $model->post_status !== 'inherit'
            && $model->post_type !== 'revision';
    }

    /**
     * Load the preview revision for a post.
     *
     * Finds the newest revision authored by the current user.
     *
     * @since 1.0.0
     *
     * @param Model $model The model to load preview for
     * @return static|null The preview model or null if not found
     */
    protected function loadPreview(self $model): ?static
    {
        return $model->revision()
            ->where('post_author', get_current_user_id())
            ->newest()
            ->first();
    }

    /**
     * Register this model as a custom post type with WordPress.
     *
     * Uses the PostTypes library to register the post type. If the post type
     * already exists, it removes the old definition first. Also configures
     * admin column visibility and registers with Layotter if configured.
     *
     * @since 1.0.0
     *
     * @return bool True if registration succeeded, false if disabled
     * @see PostTypes\PostType For the underlying registration
     */
    public function register(): bool
    {
        global $wp_post_types;

        if (!$this->register) {
            return false;
        }

        if (\post_type_exists($this->getPostType())) {
            $post_type_object = \get_post_type_object($this->getPostType());
            $this->labels = array_merge((array) \get_post_type_labels($post_type_object), $this->labels);

            $post_type_object->remove_supports();
            $post_type_object->remove_rewrite_rules();
            $post_type_object->unregister_meta_boxes();
            $post_type_object->remove_hooks();
            $post_type_object->unregister_taxonomies();

            $this->options = array_merge((array) $wp_post_types[$this->getPostType()], $this->options);
            unset($wp_post_types[$this->getPostType()]);
            \do_action('unregistered_post_type', $this->getPostType());
        }

        $names = array_merge($this->names, ['name' => $this->getPostType()]);
        $options = $this->options;
        if ($this->icon !== null) {
            $options = array_merge($this->options, [
                'menu_icon' => 'dashicons-' . preg_replace('/^dashicons-/', '', (string) $this->icon),
            ]);
        }

        $pt = new PostType($names, $options, $this->labels);
        $pt->columns()->hide($this->admin_columns_hidden);
        $pt->columns()->add($this->admin_columns);

        $idx = in_array('title', $this->admin_columns_hidden) ? 1 : 2;
        $order = [];
        $sortable = [];

        foreach (array_keys($this->admin_columns) as $k) {
            $class = static::class;
            $pt->columns()->populate(
                $k,
                function ($column, $post_id) use ($class, $k): void {
                    $r = call_user_func_array([$class, 'find'], [$post_id]);
                    echo call_user_func([$r, 'get' . ucfirst((string) $k) . 'Column']);
                }
            );
            $sortable[$k] = $k;
            $order[$k] = $idx;
            $idx++;
        }

        $order['date'] = $idx + 100;
        $pt->columns()->order($order);
        $pt->columns()->sortable($sortable);

        if (in_array('title', $this->admin_columns_hidden)) {
            $keys = array_keys($this->admin_columns);
            $first_column = reset($keys);
            add_filter(
                'list_table_primary_column',
                function ($default, $screen) use ($pt, $first_column): string {
                    if ('edit-' . $pt->name === $screen) {
                        return $first_column;
                    }

                    return $default;
                },
                10,
                2
            );
        }

        if (method_exists($pt, 'register')) {
            $pt->register();
        }

        if (method_exists($pt, 'registerPostType')) {
            $pt->registerPostType();
        }

        return true;
    }

    /**
     * Initialize the post type after WordPress registration.
     *
     * Updates the registered post type object with custom options
     * that need to be set after registration.
     *
     * @since 1.0.0
     */
    final public function init(): void
    {
        $object = \get_post_type_object($this->postType);
        foreach ($this->options as $key => $option) {
            if ($object) {
                $object->{$key} = $option;
            }
        }
    }

    /**
     * Scope query to order by a meta field.
     *
     * WordPress-specific scope that orders posts by a custom field value.
     * Uses FIELD() MySQL function for explicit ordering.
     *
     * @since 1.0.0
     *
     * @param PostBuilder $query The query builder
     * @param string $meta The meta key to order by
     * @param string $direction Sort direction ('asc' or 'desc')
     */
    public function scopeOrderByMeta(PostBuilder $query, string $meta, string $direction = 'asc'): void
    {
        $metaRows = PostMeta::where('meta_key', $meta)->orderBy('meta_value', $direction)->get();
        $postIds = $metaRows->pluck('post_id')->toArray();
        $query->orderByRaw('FIELD(ID, ' . implode(',', $postIds) . ')');
    }

    /**
     * Scope to find a post by slug or ID.
     *
     * Allows finding a post either by its post_name (slug) or ID.
     *
     * @since 1.0.0
     *
     * @param PostBuilder $query The query builder
     * @param string $slug The slug or ID to search for
     * @return PostBuilder The filtered query
     */
    public function scopeFindBySlugOrId(PostBuilder $query, string $slug): PostBuilder
    {
        return $query->where('post_name', $slug)->orWhere('ID', $slug);
    }


    /**
     * Get a formatted column value for admin list view.
     *
     * Returns the value wrapped in a link to the post edit screen.
     * Used by PostTypes column customization.
     *
     * @since 1.0.0
     *
     * @param string $which The column key (case-insensitive)
     * @return string HTML anchor element linking to edit screen
     */
    public function getColumn(string $which): string
    {
        $value = $this->{$which} ?? $this->{strtolower($which)};

        return '<a href="' . get_edit_post_link($this->ID) . '">' . $value . '</a>';
    }

    /**
     * @param $method
     * @param $parameters
     * @return mixed|string
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
     * @param $key
     * @return mixed
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

    protected function getMetaClass(): string
    {
        return PostMeta::class;
    }

    protected function getMetaForeignKey(): string
    {
        return 'post_id';
    }

    public function scopeHome(Builder $query): Builder
    {
        return $query
            ->where('ID', '=', get_options('page_on_front'))
            ->limit(1);
    }
}
