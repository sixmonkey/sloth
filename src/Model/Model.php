<?php

namespace Sloth\Model;

use Carbon\Carbon;
use Corcel\Model\Attachment;
use Corcel\Model\Post as Corcel;
use PostTypes\PostType;
use Sloth\Facades\Configure;
use Sloth\Field\CarbonFaker;
use Sloth\Field\Image;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Builder\PostBuilder;
use Corcel\Acf\FieldFactory;

class Model extends Corcel
{
    protected $names = [];
    protected $options = [];
    protected $labels = [];
    public static $layotter = false;
    public $register = true;
    public $post_content = ' ';
    protected $icon;
    protected $filtered = false;
    public $admin_columns = [];
    public $admin_columns_hidden = [];

    /**
     * @var array
     */
    protected $attributes = [
        'post_content'          => '',
        'post_title'            => '',
        'post_excerpt'          => '',
        'to_ping'               => false,
        'pinged'                => false,
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
     * Model constructor.
     *
     * @param array $attributes
     *
     * @throws \ReflectionException
     */
    public function __construct(array $attributes = [])
    {
        $reflection = new \ReflectionClass($this);
        if ($reflection->getName() === self::class) {
            $this->postType = false;
        }
        if ($this->postType === null) {
            $reflection     = new \ReflectionClass($this);
            $this->postType = strtolower($reflection->getShortName());
        }
        if (is_array($this->labels) && count($this->labels)) {
            foreach ($this->labels as &$label) {
                $label = __($label);
            }
        }
        $this->setRawAttributes(array_merge($this->attributes,
            [
                'post_type' => $this->getPostType(),
            ]),
            true);
        parent::__construct($attributes);
    }

    /**
     * @return bool
     */
    public function register()
    {

        global $wp_post_types;

        if ( ! $this->register) {
            return false;
        }

        if (\post_type_exists($this->getPostType())) {

            $post_type_object = get_post_type_object($this->getPostType());
            $this->labels     = array_merge((array)\get_post_type_labels($post_type_object), $this->labels);

            $post_type_object->remove_supports();
            $post_type_object->remove_rewrite_rules();
            $post_type_object->unregister_meta_boxes();
            $post_type_object->remove_hooks();
            $post_type_object->unregister_taxonomies();

            $this->options = array_merge((array)$wp_post_types[$this->getPostType()], $this->options);
            unset($wp_post_types[$this->getPostType()]);
            /**
             * Fires after a post type was unregistered.
             *
             * @param string $post_type Post type identifier.
             */
            do_action('unregistered_post_type', $this->getPostType());
        }

        $names   = array_merge($this->names, ['name' => $this->getPostType()]);
        $options = $this->options;
        if (isset($this->icon)) {
            $options = array_merge($this->options,
                ['menu_icon' => 'dashicons-' . preg_replace('/^dashicons-/', '', $this->icon)]);
        }
        $labels = $this->labels;

        $pt = new PostType($names, $options, $labels);

        $pt->columns()->add($this->admin_columns);

        $idx      = 2;
        $order    = [];
        $sortable = [];

        foreach ($this->admin_columns as $k => $v) {
            $class = self::class;

            $pt->columns()->populate($k,
                function ($column, $post_id) use ($class, $k) {
                    $r = call_user_func_array([$class, 'find'], [$post_id]);
                    echo call_user_func([$r, 'get' . ucfirst($k) . 'Column']);
                });

            $sortable[$k] = $k;
            $order[$k]    = $idx;
            $idx          += 1;
        }

        $order['title'] = 1;
        $order['date']  = $idx + 100;

        $pt->columns()->order($order);

        $pt->columns()->sortable($sortable);

        $pt->columns()->hide($this->admin_columns_hidden);

        if (in_array('title', $this->admin_columns_hidden)) {
            $keys         = array_keys($this->admin_columns);
            $first_column = reset($keys);
            add_filter('list_table_primary_column',
                function ($default, $screen) use ($pt, $first_column) {
                    if ('edit-' . $pt->name === $screen) {
                        $default = $first_column;
                    }

                    return $default;
                },
                10,
                2);
        }

        # fix for newer version of jjgrainger/PostTypes
        if (method_exists($pt, 'register')) {
            $pt->register();
        }
        if (method_exists($pt, 'registerPostType')) {
            $pt->registerPostType();
        }

    }

    /**
     * @return string
     */
    public function getPostType()
    {
        return $this->postType;
    }

    /**
     * @return false|string
     */
    public function getPermalinkAttribute()
    {
        return \get_permalink($this->ID);
    }


    /**
     * @return \Sloth\Field\Image
     */
    public function getPostThumbnailAttribute()
    {
        return new Image((int)$this->meta->_thumbnail_id);
    }

    /**
     *
     */
    final public function init()
    {
        // fix post_type
        $object = get_post_type_object($this->postType);
        foreach ($this->options as $key => $option) {
            if ($object) {
                $object->{$key} = $option;
            }
        }
    }

    /**
     * @return string
     */
    public function getContentAttribute()
    {
        if ( ! $this->filtered) {
            $post_content = $this->getAttribute('post_content');
            if ( ! is_null($post_content)) {
                $this->post_content = \apply_filters('the_content', $post_content);
            }
            $this->filtered = true;
        }

        return (string)$this->post_content;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function __isset($key)
    {
        return $this->acf->boolean($key);
    }

    /**
     * @param string $key
     *
     * @return mixed
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
                }


                if (Configure::check('sloth.acf.process') && Configure::read('sloth.acf.process') == true) {
                    if (in_array($acf['type'], ['date_picker', 'date_time_picker', 'time_picker'])) {
                        return empty(parent::__get($key)) ? new CarbonFaker() : Carbon::createFromFormat('Y-m-d H:i:s',
                            date('Y-m-d H:i:s', parent::__get($key)));
                    }
                    $field = FieldFactory::make($key, $this);

                    return $field ? $field->get() : null;
                }
            }
        }

        $value = parent::__get($key);

        return $value;
    }

    public function getColumn($which)
    {
        $value = $this->{$which} ?? $this->{strtolower($which)};

        return '<a href="' . get_edit_post_link($this->ID) . '">' . $value . '</a>';
    }

    public function __call($method, $parameters)
    {
        $parts = preg_split('/(?=[A-Z])/', $method);

        if ($parts[0] == 'get' && $parts[2] == 'Column') {
            return $this->getColumn($parts[1]);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $array = parent::toArray();

        foreach ($this->getMutatedAttributes() as $key) {
            if ( ! array_key_exists($key, $array)) {
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
     *
     * @param \Corcel\Model\Builder\PostBuilder $query
     * @param                                   $meta
     * @param string                            $direction
     */
    public function scopeOrderByMeta(PostBuilder $query, $meta, $direction = 'asc')
    {
        $metaRows = PostMeta::where('meta_key', $meta)->orderBy('meta_value', $direction)->get();
        $postIds  = $metaRows->pluck('post_id')->toArray();
        $query->orderByRaw('FIELD(ID, ' . implode(',', $postIds) . ')');
    }
}
