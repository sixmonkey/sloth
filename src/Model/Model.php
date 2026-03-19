<?php

declare(strict_types=1);

namespace Sloth\Model;

use Carbon\Carbon;
use Corcel\Model\Attachment;
use Corcel\Model\Post as CorcelPost;
use Sloth\Facades\Configure;
use Sloth\Field\CarbonFaker;
use Sloth\Field\Image;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Builder\PostBuilder;
use Corcel\Acf\FieldFactory;
use PostTypes\PostType;

/**
 * Base Model
 *
 * Extends Corcel's Post model to provide additional functionality
 * for WordPress custom post types and ACF integration.
 *
 * @since 1.0.0
 * @see CorcelPost For the base Corcel implementation
 *
 * @property string $post_content The processed post content (filtered)
 * @property string $post_title The post title
 * @property string $post_excerpt The post excerpt
 * @property Image $post_thumbnail The featured image
 * @property string $permalink The post permalink URL
 *
 * @example
 * ```php
 * class Project extends Model {
 *     protected $postType = 'project';
 *     public static bool $layotter = true;
 * }
 * ```
 */
class Model extends CorcelPost
{
    use PostTypeAdapter;

    /**
     * Post type names configuration for PostTypes library.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected $names = [];

    /**
     * Post type options for WordPress registration.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected $options = []; // Used by PostTypeAdapter trait's options() method

    /**
     * Post type labels for WordPress admin UI.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected $labels = [];

    /**
     * Post type taxonomies.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected $taxonomies = [];

    /**
     * Admin filters (taxonomies for filtering).
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected $filters = [];

    /**
     * Whether this post type should appear in Layotter page builder.
     *
     * Can be false to disable, true to enable with defaults,
     * or an array with configuration options.
     *
     * @since 1.0.0
     * @var mixed
     */
    public static $layotter = false;

    /**
     * Whether to register this post type with WordPress.
     *
     * @since 1.0.0
     * @var bool
     */
    public bool $register = true;

    /**
     * The post content (initialized to space to prevent null issues).
     *
     * @since 1.0.0
     * @var string
     */
    public string $post_content = ' ';

    /**
     * Menu icon for the post type in WordPress admin.
     *
     * @since 1.0.0
     * @var string|null
     */
    protected $icon = null;

    /**
     * Flag indicating if content has been filtered.
     *
     * @since 1.0.0
     * @var bool
     */
    protected bool $filtered = false;

    /**
     * Admin columns to display in post type list.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public array $admin_columns = [];

    /**
     * Admin columns to hide in post type list.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public array $admin_columns_hidden = [];

    /**
     * Default attribute values for the model.
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
     * Creates a new Model instance.
     *
     * Initializes the post type based on class name or $postType property,
     * processes labels, and sets up default attributes.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $attributes Initial attributes
     *
     * @throws \ReflectionException If class reflection fails
     */
    public function __construct(array $attributes = [])
    {
        $reflection = new \ReflectionClass($this);

        if ($reflection->getName() === self::class) {
            $this->postType = false;
        }

        if ($this->postType === null) {
            $reflection = new \ReflectionClass($this);
            $this->postType = strtolower($reflection->getShortName());
        }

        if (is_array($this->labels) && count($this->labels) > 0) {
            foreach ($this->labels as &$label) {
                $label = \__($label);
            }
        }

        $this->setRawAttributes(array_merge($this->attributes, [
            'post_type' => $this->getPostType(),
        ]), true);

        parent::__construct($attributes);
    }

    /**
     * Registers the post type with WordPress.
     *
     * If the post type already exists, it will be unregistered first
     * and then re-registered with the new configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        if (!$this->register) {
            return;
        }

        $postTypeName = $this->name();
        if (!$postTypeName || $postTypeName === '') {
            return;
        }

        $names = $this->names;
        if (!isset($names['name'])) {
            $names['name'] = $postTypeName;
        }

        $options = $this->options ?? [];
        $labels = $this->labels ?? [];

        $cpt = new PostType($names, $options, $labels);

        if (isset($this->icon)) {
            $cpt->icon($this->icon);
        }

        foreach ($this->taxonomies() as $taxonomy) {
            $cpt->taxonomy($taxonomy);
        }

        foreach ($this->filters() as $filter) {
            $cpt->filters([$filter]);
        }

        if (!empty($this->admin_columns) || !empty($this->admin_columns_hidden)) {
            $columns = $cpt->columns();
            $this->configureColumnsFromAdapter($columns);
        }

        $cpt->register();
        $cpt->registerPostType();
    }

    /**
     * Configure admin columns for the post type.
     *
     * @since 1.0.0
     *
     * @param string $postTypeName
     *
     * @return void
     */
    private function configureColumns(string $postTypeName): void
    {
        $hiddenColumns = $this->admin_columns_hidden ?? [];
        $addedColumns = $this->admin_columns ?? [];

        if (!empty($addedColumns) || !empty($hiddenColumns)) {
            add_filter('manage_' . $postTypeName . '_posts_columns', static function ($columns) use ($addedColumns, $hiddenColumns) {
                if (!empty($hiddenColumns)) {
                    foreach ($hiddenColumns as $col) {
                        unset($columns[$col]);
                    }
                }

                foreach ($addedColumns as $key => $label) {
                    $columns[$key] = $label;
                }

                return $columns;
            });

            if (!empty($addedColumns)) {
                $class = static::class;
                add_action('manage_' . $postTypeName . '_posts_custom_column', static function ($column, $postId) use ($addedColumns, $class) {
                    if (isset($addedColumns[$column])) {
                        $model = $class::find($postId);
                        if ($model) {
                            if (method_exists($model, 'getColumn')) {
                                echo $model->getColumn($column);
                            } else {
                                $value = $model->{$column} ?? '';
                                echo \esc_html((string) $value);
                            }
                        }
                    }
                }, 10, 2);
            }
        }
    }

    /**
     * Gets the post type identifier for this model.
     *
     * @since 1.0.0
     *
     * @return string|false The post type name or false if not set
     */
    public function getPostType(): string|false
    {
        return $this->postType;
    }

    /**
     * Gets the permalink URL for this post.
     *
     * @since 1.0.0
     *
     * @return string|false The permalink URL or false if not available
     */
    public function getPermalinkAttribute(): string|false
    {
        return \get_permalink($this->ID);
    }

    /**
     * Gets the featured image as an Image object.
     *
     * @since 1.0.0
     *
     * @return Image The featured image wrapper object
     */
    public function getPostThumbnailAttribute(): Image
    {
        return new Image((int) $this->meta->_thumbnail_id);
    }

    /**
     * Initializes post type options from WordPress.
     *
     * Called after WordPress has registered the post type to
     * apply any custom configuration.
     *
     * @since 1.0.0
     *
     * @uses get_post_type_object() To get WordPress post type object
     */
    final public function init(): void
    {
        $object = get_post_type_object($this->postType);

        foreach ($this->options as $key => $option) {
            if ($object !== null) {
                $object->{$key} = $option;
            }
        }
    }

    /**
     * Gets the filtered post content.
     *
     * The content is processed through WordPress's the_content filter
     * on first access and then cached.
     *
     * @since 1.0.0
     *
     * @return string The filtered post content
     *
     * @uses apply_filters() To apply WordPress content filters
     */
    public function getContentAttribute(): string
    {
        if (!$this->filtered) {
            $post_content = $this->getAttribute('post_content');
            if ($post_content !== null) {
                $this->post_content = (string) \apply_filters('the_content', $post_content);
            }
            $this->filtered = true;
        }

        return $this->post_content;
    }

    /**
     * Checks if an ACF field exists.
     *
     * @since 1.0.0
     *
     * @param string $key The field key or name
     *
     * @return bool True if the field exists and has a truthy value
     */
    public function __isset($key): bool
    {
        return $this->acf->boolean($key);
    }

    /**
     * Gets an attribute value, including ACF field support.
     *
     * This magic method provides access to ACF fields and processes
     * special field types like images and date pickers.
     *
     * @since 1.0.0
     *
     * @param string $key The attribute or field key
     *
     * @return mixed The attribute value
     */
    public function __get($key)
    {
        if (function_exists('acf_maybe_get_field')) {
            $acf = acf_maybe_get_field($key, $this->getAttribute('ID'), false);

            if ($acf) {
                if ($acf['type'] === 'image') {
                    $attachment = Attachment::find(parent::__get($key));
                    if (is_object($attachment)) {
                        return new Image($attachment->url);
                    }

                    return new Image(parent::__get($key));
                }

                if (Configure::check('sloth.acf.process') && Configure::read('sloth.acf.process') === true) {
                    if (in_array($acf['type'], ['date_picker', 'date_time_picker', 'time_picker'], true)
                        && empty(parent::__get($key))) {
                        return new CarbonFaker();
                    }

                    $field = FieldFactory::make($key, $this, $acf['type']);

                    return $field?->get();
                }
            }
        }

        return parent::__get($key);
    }

    /**
     * Gets a formatted column value for admin display.
     *
     * @since 1.0.0
     *
     * @param string $which The column identifier
     *
     * @return string The HTML-formatted column value with edit link
     */
    public function getColumn(string $which): string
    {
        $value = $this->{$which} ?? $this->{strtolower($which)} ?? '';

        return '<a href="' . get_edit_post_link($this->ID) . '">' . $value . '</a>';
    }

    /**
     * Handles dynamic method calls for column getters.
     *
     * Converts calls like getTitleColumn to the appropriate getColumn call.
     *
     * @since 1.0.0
     *
     * @param string $method The method name called
     * @param array<int, mixed> $parameters The method parameters
     *
     * @return mixed The result of the call
     */
    public function __call($method, $parameters)
    {
        $parts = preg_split('/(?=[A-Z])/', $method, -1, PREG_SPLIT_NO_EMPTY);

        if (($parts[0] ?? '') === 'get' && ($parts[2] ?? '') === 'Column') {
            return $this->getColumn($parts[1]);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * Converts the model to an array with mutated attributes.
     *
     * Includes any accessors that return mutated values.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> The array representation
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        foreach ($this->getMutatedAttributes() as $key) {
            if (!array_key_exists($key, $array)) {
                $array[$key] = $this->{$key};
            }
        }

        if (isset($this->hidden) && is_array($this->hidden)) {
            foreach ($this->hidden as $k) {
                unset($array[$k]);
            }
        }

        return $array;
    }

    /**
     * Orders results by a meta field value.
     *
     * @since 1.0.0
     *
     * @param PostBuilder $query The query builder instance
     * @param string $meta The meta key to order by
     * @param string $direction The sort direction (asc or desc)
     *
     * @return void
     */
    public function scopeOrderByMeta(PostBuilder $query, string $meta, string $direction = 'asc'): void
    {
        $metaRows = PostMeta::where('meta_key', $meta)
            ->orderBy('meta_value', $direction)
            ->get();
        $postIds = $metaRows->pluck('post_id')->toArray();
        $query->orderByRaw('FIELD(ID, ' . implode(',', $postIds) . ')');
    }
}
