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

    const CREATED_AT = 'post_date';

    const UPDATED_AT = 'post_modified';

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
     * @var array
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
     * @var array
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
     * @param array $attributes
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
     * @return void
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
     * @param $query
     * @return PostBuilder
     */
    public function newEloquentBuilder($query): PostBuilder
    {
        return new PostBuilder($query);
    }

    /**
     * @return Builder
     */
    public function newQuery()
    {
        return $this->postType
            ? parent::newQuery()->type($this->postType)
            : parent::newQuery();
    }

    /**
     * @return HasOne
     */
    public function thumbnail(): HasOne
    {
        return $this->hasOne(ThumbnailMeta::class, 'post_id')
            ->where('meta_key', '_thumbnail_id');
    }

    /**
     * @return BelongsToMany
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
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'comment_post_ID');
    }

    /**
     * @return BelongsTo
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_author');
    }

    /**
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'post_parent');
    }

    /**
     * @return HasMany
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent');
    }

    /**
     * @return HasMany
     */
    public function attachment(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'attachment');
    }

    /**
     * @return HasMany
     */
    public function revision(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'revision');
    }

    /**
     * @return string
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
     * @return bool|string
     */
    public function getPermalinkAttribute(): bool|string
    {
        return \get_permalink($this->ID);
    }

    /**
     * @return Image
     */
    public function getPostThumbnailAttribute(): Image
    {
        return new Image((int)$this->meta->_thumbnail_id);
    }

    /**
     * @return Image
     */
    public function getImageAttribute(): Image
    {
        return $this->getPostThumbnailAttribute();
    }

    /**
     * @return array
     */
    public function getTermsAttribute(): array
    {
        return $this->taxonomies->groupBy(fn($taxonomy
        ) => $taxonomy->taxonomy === 'post_tag' ? 'tag' : $taxonomy->taxonomy)->map(fn($group
        ) => $group->mapWithKeys(fn($item) => [$item->term->slug => $item->term->name]))->toArray();
    }

    /**
     * @return string
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
     * @return array
     */
    public function getKeywordsAttribute(): array
    {
        return collect($this->terms)->map(fn($taxonomy) => collect($taxonomy)->values())->collapse()->toArray();
    }

    /**
     * @return string
     */
    public function getKeywordsStrAttribute(): string
    {
        return implode(',', (array)$this->keywords);
    }

    /**
     * @return string
     */
    public function getPostType(): string
    {
        return (string)$this->postType;
    }

    /**
     * @param string $taxonomy
     * @param string $term
     * @return bool
     */
    public function hasTerm(string $taxonomy, string $term): bool
    {
        return isset($this->terms[$taxonomy][$term]);
    }

    /**
     * @return bool|string
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
     * @param $attributes
     * @param $connection
     * @return $this
     */
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
     * @param Model $model
     * @return bool
     */
    protected function shouldLoadPreview(self $model): bool
    {
        return isset($_GET['preview'])
            && is_user_logged_in()
            && $model->post_status !== 'inherit'
            && $model->post_type !== 'revision';
    }

    /**
     * @param Model $model
     * @return $this|null
     */
    protected function loadPreview(self $model): ?static
    {
        return $model->revision()
            ->where('post_author', get_current_user_id())
            ->newest()
            ->first();
    }

    /**
     * @return bool
     */
    public function register(): bool
    {
        global $wp_post_types;

        if (!$this->register) {
            return false;
        }

        if (\post_type_exists($this->getPostType())) {
            $post_type_object = \get_post_type_object($this->getPostType());
            $this->labels = array_merge((array)\get_post_type_labels($post_type_object), $this->labels);

            $post_type_object->remove_supports();
            $post_type_object->remove_rewrite_rules();
            $post_type_object->unregister_meta_boxes();
            $post_type_object->remove_hooks();
            $post_type_object->unregister_taxonomies();

            $this->options = array_merge((array)$wp_post_types[$this->getPostType()], $this->options);
            unset($wp_post_types[$this->getPostType()]);
            \do_action('unregistered_post_type', $this->getPostType());
        }

        $names = array_merge($this->names, ['name' => $this->getPostType()]);
        $options = $this->options;
        if ($this->icon !== null) {
            $options = array_merge($this->options, [
                'menu_icon' => 'dashicons-' . preg_replace('/^dashicons-/', '', (string)$this->icon),
            ]);
        }

        $pt = new PostType($names, $options, $this->labels);
        $pt->columns()->hide($this->admin_columns_hidden);
        $pt->columns()->add($this->admin_columns);

        $idx = in_array('title', $this->admin_columns_hidden) ? 1 : 2;
        $order = [];
        $sortable = [];

        foreach ($this->admin_columns as $k => $v) {
            $class = static::class;
            $pt->columns()->populate(
                $k,
                function ($column, $post_id) use ($class, $k): void {
                    $r = call_user_func_array([$class, 'find'], [$post_id]);
                    echo call_user_func([$r, 'get' . ucfirst((string)$k) . 'Column']);
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
                        $default = $first_column;
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
     * @return void
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
     * @param PostBuilder $query
     * @param string $meta
     * @param string $direction
     * @return void
     */
    public function scopeOrderByMeta(PostBuilder $query, string $meta, string $direction = 'asc'): void
    {
        $metaRows = PostMeta::where('meta_key', $meta)->orderBy('meta_value', $direction)->get();
        $postIds = $metaRows->pluck('post_id')->toArray();
        $query->orderByRaw('FIELD(ID, ' . implode(',', $postIds) . ')');
    }

    /**
     * @param PostBuilder $query
     * @param string $slug
     * @return PostBuilder
     */
    public function scopeFindBySlugOrId(PostBuilder $query, string $slug): PostBuilder
    {
        return $query->where('post_name', $slug)->orWhere('ID', $slug);
    }


    /**
     * @param string $which
     * @return string
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
    public function __get($key)
    {
        $value = parent::__get($key);

        if ($value === null && !property_exists($this, $key)) {
            return $this->meta->$key;
        }

        return $value;
    }

    /**
     * @return array
     */
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

    /**
     * @param Builder $query
     * @return Builder
     */
    public function scopeHome(Builder $query): Builder
    {
        return $query
            ->where('ID', '=', get_options('page_on_front'))
            ->limit(1);
    }
}
