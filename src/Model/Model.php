<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model as CorcelModel;
use Corcel\Model\Comment;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Meta\ThumbnailMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Sloth\Field\Image;
use Sloth\Model\Builder\PostBuilder;
use Sloth\Model\Concerns\AdminColumns;
use Sloth\Model\Concerns\PostScopes;
use Sloth\Model\Traits\HasACF;
use Sloth\Model\Traits\HasAliases;
use Sloth\Model\Traits\HasCustomTimestamps;
use Sloth\Model\Traits\HasMetaFields;
use Sloth\Model\Traits\HasOrderScopes;

/**
 * Base Model class for WordPress post types.
 *
 * This class extends Corcel\Model directly to provide a foundation for all custom
 * post types in the Sloth framework. It includes ACF integration, taxonomy
 * relationships, and WordPress-specific query scopes.
 *
 * ## Independence from Corcel
 *
 * This model does NOT extend Corcel\Model\Post, Corcel\Model\User, or any other
 * Corcel model class. Instead, it implements all necessary features directly or
 * uses Sloth's own trait implementations. This ensures full control over attribute
 * resolution and prevents issues like infinite recursion in alias handling.
 *
 * ## Traits
 *
 * This model uses Sloth's own trait implementations:
 * - HasACF: ACF field integration
 * - HasAliases: Attribute alias resolution (with critical recursion fix)
 * - HasCustomTimestamps: WordPress GMT timestamp support
 * - HasMetaFields: WordPress meta field management
 * - HasOrderScopes: Query scopes for ordering
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
    use AdminColumns;
    use PostScopes;
    use HasACF;
    use HasAliases;
    use HasCustomTimestamps;
    use HasMetaFields;
    use HasOrderScopes;

    /**
     * Post type identifier for this model.
     *
     * @var string|false
     */
    protected $postType = false;

    /**
     * Post types registered with this model.
     *
     * @var array<string, class-string>
     */
    protected static array $postTypes = [];

    public const CREATED_AT = 'post_date';

    public const UPDATED_AT = 'post_modified';

    protected $table = 'posts';

    protected $primaryKey = 'ID';

    protected $dates = ['post_date', 'post_date_gmt', 'post_modified', 'post_modified_gmt'];

    protected $with = ['meta'];

    protected array $names = [];

    protected array $options = [];

    protected array $labels = [];

    public static $layotter = false;

    public bool $register = true;

    public string $post_content = ' ';

    protected $icon;

    protected bool $filtered = false;

    protected static bool $globalScopesBooted = false;

    /**
     * Aliases for attribute access.
     *
     * Maps alternative property names to their original database columns.
     *
     * @var array<string, string|array>
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
     * @param array<string, mixed> $attributes Initial model attributes
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
     * Create a model instance from a database row.
     *
     * Handles post type instantiation - returns the correct class based
     * on the post_type attribute.
     *
     * @param object|array $attributes The database row attributes
     * @param string|null $connection The connection name
     * @return static The model instance
     * @since 1.0.0
     *
     */
    #[\Override]
    public function newFromBuilder($attributes = [], $connection = null): static
    {
        $attributes = (array)$attributes;
        $class = static::class;

        if (isset($attributes['post_type']) && $attributes['post_type']) {
            if (isset(static::$postTypes[$attributes['post_type']])) {
                $class = static::$postTypes[$attributes['post_type']];
            }
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
     * Check if we should load the preview version of a post.
     *
     * Returns true when preview mode is active, user is logged in,
     * and it's not a revision or inherited post.
     *
     * @param Model $model The model to check
     * @return bool True if preview should be loaded
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
     * Load the preview revision for a post.
     *
     * Finds the newest revision authored by the current user.
     *
     * @param Model $model The model to load preview for
     * @return static|null The preview model or null if not found
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

    /**
     * Create a new Eloquent query builder for the model.
     *
     * Uses Sloth's custom PostBuilder which adds WordPress-specific
     * query methods like whereStatus(), withArchives(), etc.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query The base query builder
     * @return PostBuilder The custom post builder instance
     * @since 1.0.0
     *
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
     * @return Builder The filtered query builder
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

    /**
     * Register a post type class to be instantiated for specific post types.
     *
     * @param string $name The post type slug
     * @param class-string $class The fully qualified class name
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
     * @since 1.0.0
     */
    public static function clearRegisteredPostTypes(): void
    {
        static::$postTypes = [];
    }

    /**
     * Get the post thumbnail relationship.
     *
     * @return HasOne The thumbnail meta relationship
     * @since 1.0.0
     *
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
     * @return BelongsToMany The taxonomies relationship
     * @since 1.0.0
     *
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
     * @return HasMany The comments relationship
     * @since 1.0.0
     *
     * @see \Corcel\Model\Comment
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    /**
     * Get the post author.
     *
     * @return BelongsTo The author relationship
     * @since 1.0.0
     *
     * @see \Sloth\Model\User
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    /**
     * Get the parent post if this is a hierarchical post type.
     *
     * @return BelongsTo The parent post relationship
     * @since 1.0.0
     *
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'post_parent');
    }

    /**
     * Get child posts (for hierarchical post types like pages).
     *
     * @return HasMany The children relationship
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
     * Filters to only return posts with post_type 'attachment'.
     *
     * @return HasMany The attachments relationship
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
     * Filters to only return posts with post_type 'revision'.
     *
     * @return HasMany The revisions relationship
     * @since 1.0.0
     *
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
     * @return string The filtered HTML content
     * @since 1.0.0
     *
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

        return (string)$this->post_content;
    }

    /**
     * Get the post excerpt with shortcodes stripped.
     *
     * @return string The stripped excerpt
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
     * @return bool|string The permalink URL or false on failure
     * @since 1.0.0
     *
     */
    public function getPermalinkAttribute(): bool|string
    {
        return \get_permalink($this->ID);
    }

    /**
     * Get the featured image/thumbnail for this post.
     *
     * @return Image The Image object wrapping the thumbnail
     * @since 1.0.0
     *
     * @see \Sloth\Field\Image
     */
    public function getPostThumbnailAttribute(): Image
    {
        return new Image((int)$this->meta->_thumbnail_id);
    }

    /**
     * Get the featured image (alias for getPostThumbnailAttribute).
     *
     * @return Image The Image object wrapping the thumbnail
     * @since 1.0.0
     *
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
     * @return array<string, array<string, string>> Grouped terms by taxonomy
     * @since 1.0.0
     *
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
     * @return string The main category name
     * @since 1.0.0
     *
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
     * @return array<string> All term names as flat array
     * @since 1.0.0
     *
     * @see getTermsAttribute() For the source data
     */
    public function getKeywordsAttribute(): array
    {
        return collect($this->terms)->map(fn($taxonomy) => collect($taxonomy)->values())->collapse()->toArray();
    }

    /**
     * Get all keywords as a comma-separated string.
     *
     * @return string Comma-separated keyword string
     * @since 1.0.0
     *
     * @see getKeywordsAttribute() For the source data
     */
    public function getKeywordsStrAttribute(): string
    {
        return implode(',', (array)$this->keywords);
    }

    /**
     * Get the post type identifier.
     *
     * @return string The post type (e.g., 'post', 'page', or custom post type)
     * @since 1.0.0
     *
     */
    public function getPostType(): string
    {
        return (string)$this->postType;
    }

    /**
     * Check if this post has a specific term.
     *
     * @param string $taxonomy The taxonomy name (e.g., 'category', 'post_tag')
     * @param string $term The term slug to check for
     * @return bool True if the post has this term
     * @since 1.0.0
     *
     * @see getTermsAttribute() For the terms data
     */
    public function hasTerm($taxonomy, $term): bool
    {
        return isset($this->terms[$taxonomy]) &&
            isset($this->terms[$taxonomy][$term]);
    }

    /**
     * Get the post format (e.g., 'standard', 'aside', 'gallery').
     *
     * Looks up the post_format taxonomy for this post.
     *
     * @return bool|string The post format slug or false if not found
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
     * Get labels for WordPress post type registration.
     *
     * If $this->labels is already populated, returns those labels
     * (with translation). Otherwise, generates labels from $this->names.
     *
     * ## Label Generation
     *
     * When $labels is empty, labels are auto-generated from $names:
     * - $names['singular'] → singular_name, parent_item, edit_item, etc.
     * - $names['plural'] → name, menu_name, all_items, search_items, etc.
     *
     * Falls back to ucfirst($postType) for singular and singular + 's' for plural.
     *
     * @return array<string, string> WordPress post type labels
     * @see getRegistrationArgs() For using labels in post type registration
     *
     * @since 1.0.0
     */
    public function getLabels(): array
    {
        if ($this->labels !== []) {
            $labels = $this->labels;
            foreach ($labels as $key => $label) {
                if (is_string($label)) {
                    $labels[$key] = __($label);
                }
            }

            return $labels;
        }

        $singular = $this->names['singular'] ?? ucfirst($this->getPostType());
        $plural = $this->names['plural'] ?? $singular . 's';

        return [
            'name' => __($plural),
            'singular_name' => __($singular),
            'add_new' => __('Add New'),
            'add_new_item' => sprintf(__('Add New %s'), __($singular)),
            'edit_item' => sprintf(__('Edit %s'), __($singular)),
            'new_item' => sprintf(__('New %s'), __($singular)),
            'view_item' => sprintf(__('View %s'), __($singular)),
            'view_items' => sprintf(__('View %s'), __($plural)),
            'search_items' => sprintf(__('Search %s'), __($plural)),
            'not_found' => sprintf(__('No %s found'), __($plural)),
            'not_found_in_trash' => sprintf(__('No %s found in Trash'), __($plural)),
            'parent_item_colon' => sprintf(__('Parent %s:'), __($singular)),
            'all_items' => sprintf(__('All %s'), __($plural)),
            'archives' => sprintf(__('%s Archives'), __($singular)),
            'attributes' => sprintf(__('%s Attributes'), __($singular)),
            'insert_into_item' => sprintf(__('Insert into %s'), __($singular)),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s'), __($singular)),
            'filter_items_list' => sprintf(__('Filter %s list'), __($plural)),
            'items_list_navigation' => sprintf(__('%s list navigation'), __($plural)),
            'items_list' => sprintf(__('%s list'), __($plural)),
            'menu_name' => __($plural),
            'name_admin_bar' => __($singular),
            'popular_items' => sprintf(__('Popular %s'), __($singular)),
            'separate_items_with_commas' => sprintf(__('Separate %s with commas'), __($plural)),
            'add_or_remove_items' => sprintf(__('Add or remove %s'), __($plural)),
            'choose_from_most_used' => sprintf(__('Choose from the most used %s'), __($plural)),
            'not_found_in_trash' => sprintf(__('No %s found in Trash'), __($plural)),
        ];
    }

    /**
     * Get registration arguments for WordPress post type registration.
     *
     * Builds and returns the arguments array required by WordPress's
     * register_post_type() function. This includes:
     * - Labels generated via getLabels() (from $this->labels or $this->names)
     * - Options from $this->options
     * - Menu icon (dashicons) from $this->icon
     * - Existing post type settings (if already registered) merged in
     *
     * If the post type already exists (e.g., from a plugin), this method
     * preserves any existing labels and options while allowing the model
     * to override them. This enables seamless re-registration of post types.
     *
     * ## Usage
     *
     * Called by Plugin::loadModels() to get the registration arguments:
     * ```php
     * register_post_type($model->getPostType(), $model->getRegistrationArgs());
     * ```
     *
     * ## Label Merging
     *
     * When a post type already exists, existing labels are merged with
     * model-defined labels. Model labels take precedence, allowing
     * customization while preserving settings from other sources.
     *
     * @return array<string, mixed> Arguments for register_post_type()
     * @see unregisterExisting() For removing an existing post type before re-registration
     * @see registerColumnHooks() For registering admin list columns
     * @see \register_post_type() WordPress function
     *
     * @since 1.0.0
     */
    public function getRegistrationArgs(): array
    {
        $args = array_merge(
            [
                'public' => true,
                'hierarchical' => false,
                'supports' => [
                    'title',
                    'editor',
                    'excerpt',
                    'author',
                    'thumbnail',
                    'revisions',
                    'page-attributes',
                    'post-formats',
                ],
                'menu_position' => 5,
                'show_ui' => true,

            ],
            $this->options
        );
        $args['labels'] = $this->getLabels();

        if ($this->icon !== null) {
            $args['menu_icon'] = 'dashicons-' . preg_replace('/^dashicons-/', '', (string)$this->icon);
        }

        if (\post_type_exists($this->getPostType())) {
            $post_type_object = \get_post_type_object($this->getPostType());
            $args['labels'] = array_merge(
                (array)\get_post_type_labels($post_type_object),
                $args['labels']
            );
            global $wp_post_types;
            $args = array_merge((array)$wp_post_types[$this->getPostType()], $args);
        }

        return $args;
    }

    /**
     * Unregister an existing post type to allow re-registration.
     *
     * This method removes a previously registered post type from WordPress
     * by:
     * - Removing all supports (title, editor, thumbnails, etc.)
     * - Clearing rewrite rules
     * - Unregistering meta boxes
     * - Removing all hooks attached to the post type
     * - Unregistering associated taxonomies
     * - Removing from the global $wp_post_types array
     * - Firing the 'unregistered_post_type' action
     *
     * ## Why Unregister?
     *
     * WordPress does not allow re-registering post types. If a post type
     * already exists (from a theme, plugin, or WordPress core), attempting
     * to register it again will fail silently. This method cleanly removes
     * the existing registration so our model can define the post type
     * with its own settings.
     *
     * ## Safety
     *
     * If the post type does not exist, this method returns early without
     * error. Posts of that type are not affected—only the registration
     * metadata is removed.
     *
     * ## Usage
     *
     * Called before register_post_type() in Plugin::loadModels():
     * ```php
     * $model->unregisterExisting();
     * register_post_type($model->getPostType(), $model->getRegistrationArgs());
     * ```
     *
     * @since 1.0.0
     * @see getRegistrationArgs() For getting the new registration arguments
     * @see \unregister_post_type() WordPress function (internal)
     * @see \do_action('unregistered_post_type') Action fired after unregistration
     */
    public function unregisterExisting(): void
    {
        if (!\post_type_exists($this->getPostType())) {
            return;
        }

        $post_type_object = \get_post_type_object($this->getPostType());
        $post_type_object->remove_supports();
        $post_type_object->remove_rewrite_rules();
        $post_type_object->unregister_meta_boxes();
        $post_type_object->remove_hooks();
        $post_type_object->unregister_taxonomies();
        global $wp_post_types;
        unset($wp_post_types[$this->getPostType()]);
        \do_action('unregistered_post_type', $this->getPostType());
    }

    /**
     * Get a formatted column value for admin list view.
     *
     * Returns the value wrapped in a link to the post edit screen.
     * Used by PostTypes column customization.
     *
     * @param string $which The column key (case-insensitive)
     * @return string HTML anchor element linking to edit screen
     * @since 1.0.0
     *
     */
    public function getColumn(string $which): string
    {
        $value = $this->{$which} ?? $this->{strtolower($which)};

        return '<a href="' . get_edit_post_link($this->ID) . '">' . $value . '</a>';
    }

    /**
     * Get the ACF key for this post.
     *
     * @return string The ACF field group key (post ID)
     * @since 1.0.0
     *
     */
    public function getAcfKey(): ?string
    {
        return (string)$this->getAttribute('ID');
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

    /**
     * @param $key
     * @return mixed
     */
    #[\Override]
    public function __isset($key): bool
    {
        $exists = parent::__isset($key);

        if ($exists) {
            return true;
        }

        if ($this->hasCast($key)) {
            return true;
        }

        if ($this->relationLoaded('meta') && $this->meta->contains('meta_key', $key)) {
            return true;
        }

        if (method_exists($this, 'getAcfKey') && isset(\Sloth\Model\Traits\HasACF::$acfFieldCache[$this->getAcfKey()])
            && \Sloth\Model\Traits\HasACF::$acfFieldCache[$this->getAcfKey()]->has($key)) {
            return true;
        }

        if (function_exists('acf_maybe_get_field') && method_exists($this, 'getAcfKey')) {
            return acf_maybe_get_field($key, $this->getAcfKey()) !== false;
        }

        return false;
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

    /**
     * Get the meta class for this model.
     *
     * @return string The fully qualified class name of the meta model
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
     * @return string The foreign key name
     * @since 1.0.0
     *
     */
    protected function getMetaForeignKey(): string
    {
        return 'post_id';
    }
}
