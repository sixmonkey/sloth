<?php

declare(strict_types=1);

namespace Sloth\Model;

use Carbon\Carbon;
use Corcel\Model\Attachment;
use Corcel\Model\Post as CorcelPost;
use PostTypes\PostType;
use Sloth\Facades\Configure;
use Sloth\Field\CarbonFaker;
use Sloth\Field\Image;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Builder\PostBuilder;
use Corcel\Acf\FieldFactory;

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
    protected $options = [];

    /**
     * Post type labels for WordPress admin UI.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected $labels = [];

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
    protected ?string $icon = null;

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
     * @return bool True if registered successfully, false if $register is false
     *
     * @uses PostType For post type registration
     * @uses add_filter() For list table column customization
     */
    public function register(): bool
    {
        global $wp_post_types;

        if (!$this->register) {
            return false;
        }

        if (\post_type_exists($this->getPostType())) {
            $post_type_object = get_post_type_object($this->getPostType());
            $this->labels = array_merge((array) \get_post_type_labels($post_type_object), $this->labels);

            $post_type_object->remove_supports();
            $post_type_object->remove_rewrite_rules();
            $post_type_object->unregister_meta_boxes();
            $post_type_object->remove_hooks();
            $post_type_object->unregister_taxonomies();

            $this->options = array_merge((array) $wp_post_types[$this->getPostType()], $this->options);
            unset($wp_post_types[$this->getPostType()]);

            do_action('unregistered_post_type', $this->getPostType());
        }

        $names = array_merge($this->names, ['name' => $this->getPostType()]);
        $options = $this->options;

        if (isset($this->icon)) {
            $options = array_merge($this->options, [
                'menu_icon' => 'dashicons-' . preg_replace('/^dashicons-/', '', $this->icon),
            ]);
        }

        $labels = $this->labels;
        $pt = new PostType($names, $options, $labels);

        $pt->columns()->hide($this->admin_columns_hidden);
        $pt->columns()->add($this->admin_columns);

        $order = ['title' => 1];
        $idx = in_array('title', $this->admin_columns_hidden, true) ? 1 : 2;
        $order = [];
        $sortable = [];

        foreach ($this->admin_columns as $k => $v) {
            $class = self::class;

            $pt->columns()->populate(
                $k,
                static function ($column, $post_id) use ($class, $k): void {
                    $r = call_user_func_array([$class, 'find'], [$post_id]);
                    echo call_user_func([$r, 'get' . ucfirst($k) . 'Column']);
                }
            );

            $sortable[$k] = $k;
            $order[$k] = $idx;
            $idx += 1;
        }

        $order['date'] = $idx + 100;

        $pt->columns()->order($order);
        $pt->columns()->sortable($sortable);

        if (in_array('title', $this->admin_columns_hidden, true)) {
            $keys = array_keys($this->admin_columns);
            $first_column = reset($keys);

            add_filter(
                'list_table_primary_column',
                static function ($default, $screen) use ($pt, $first_column): string {
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
