<?php
/**
 * User: Kremer
 * Date: 28.12.17
 * Time: 13:22
 */

namespace Sloth\Layotter;


use Brain\Hierarchy\Finder\FoldersTemplateFinder;
use \Brain\Hierarchy\QueryTemplate;
use Sloth\Facades\Configure;
use Sloth\Utility\Utility;
use Sloth\Facades\View;


final class Layotter extends \Singleton
{
    public static $disabled_post_types = ['attachement'];
    public static $layouts_for_post_type = [
    ];
    public static $layouts_for_template = [
    ];
    protected static $settings_weight = [
        'layouts_for_template'  => 'get_template',
        'layouts_for_post_type' => 'get_post_type',
    ];
    public static $enabled_post_types = [];

    /**
     * Gets template for current page.
     *
     * @return string
     */
    private static function get_template()
    {
        $pathinfo = pathinfo(get_page_template_slug());

        return $pathinfo['filename'];
    }

    /**
     * sets custom column classes for layotter. Defaults to bootstrap default classes
     *
     * @return array
     */
    public function custom_column_classes($default_classes)
    {
        $layotter_custom_classes = \Configure::read('layotter_custom_classes');

        for ($i = 1; $i <= 12; $i++) {
            $column_classes[Utility::float2fraction($i / 12)] = 'col-lg-' . $i;
        }

        if ($layotter_custom_classes) {
            $column_classes = array_merge($column_classes, $layotter_custom_classes);
        }


        return $column_classes;
    }

    /**
     * Gets post_type of current post.
     *
     * @return string
     */
    private static function get_post_type()
    {
        global $post;

        return $post->post_type;
    }

    /**
     * Wrapper for layotter/enabled_post_types.
     *
     * Enables Layotter for ANY post_type by default, use dflLayotter::disable_for_post_type to prevent this
     *
     * @param mixed $post_types Passed by default but not used, as this shall be overwritten
     *
     * @return string
     */
    public static function enabled_post_types($post_types)
    {
        $post_types = self::$enabled_post_types;
        $all_types  = get_post_types(['public' => true]); #get all public post_types

        foreach ($all_types as $post_type => $data) {
            if ( ! in_array($post_type,
                self::$disabled_post_types)) { #if the post_type isn't in $disabled_post_types
                $post_types[] = $post_type; # enable Layotter for it
            }
        }

        return $post_types;
    }

    /**
     * Wrapper for layotter/rows/allowed_layouts.
     *
     * Does some magic to set allowed Layotter-row-layouts according to current post_type respectively current page's
     * template. Use dflLayotter::set_layouts_for_post_type($my_post_type) respectively
     * dflLayotter::set_layouts_for_template to do so. layouts for template are more important than layouts for post
     * type!!
     *
     * @param array $row_layouts Passed by default and will be passed through, if there wasn't set anything for this
     *                           post_type or template
     *
     * @return array
     */
    public static function allowed_row_layouts($row_layouts)
    {
        foreach (self::$settings_weight as $setting => $getter) { # loop through both settings arrays ($layouts_for_post_type and $layouts_for_template) and see for settings
            if (isset(self::${$setting}[call_user_func('self::' . $getter)])) {
                return self::${$setting}[call_user_func('self::' . $getter)]; # return setting, if exists
            }
        }

        return Configure::read('theme.layotter.row_layouts') ? Configure::read('theme.layotter.row_layouts') : $row_layouts; # else return defaults
    }

    /**
     * Wrapper for layotter/rows/default_row_layout.
     *
     * By default Layotter uses the setting for default_row_layout no matter if it's enabled or not, so let's make
     * Layotter a bit more intelligent ;-)
     *
     * @param $row_layout The default default layout :-) Will be passed through, if nothing is set for post_type or
     *                    template
     *
     * @return string
     */
    public static function default_row_layout($row_layout)
    {
        foreach (self::$settings_weight as $setting => $getter) {
            if (isset(self::${$setting}[call_user_func('self::' . $getter)])) {
                return reset(self::${$setting}[call_user_func('self::' . $getter)]);
            }
        }

        $theme_layouts = Configure::read('theme.layotter.row_layouts');

        if (is_array($theme_layouts)) {
            return reset($theme_layouts);
        }

        return $row_layout;
    }

    /**
     *
     * Setter for Layouts by post_type
     *
     * @param mixed $post_type The post_type's slug for which we want to set the layouts
     * @param array $layouts   The layouts that will be enabled in Layotter's convention (e.g. ['1/2 1/2', '1/3 1/3 1/3']
     *
     * @return void
     */
    public static function set_layouts_for_post_type($post_type, array $layouts)
    {
        self::$layouts_for_post_type[$post_type] = $layouts;
    }


    /**
     *
     * Setter for Layouts by template
     *
     * @param mixed $template The template's slug without extension for which we want to set the layouts
     * @param array $layouts  The layouts that will be enabled in Layotter's convention (e.g. ['1/2 1/2', '1/3 1/3 1/3']
     *
     * @return void
     */
    public static function set_layouts_for_template($template, array $layouts)
    {
        self::$layouts_for_template[$template] = $layouts;
    }

    /**
     *
     * Explicitly disable Layotter for a post_type
     * Needed, as Layotter now is enabled for ANY post_type
     *
     * @param mixed $post_type The post_types's slug want to disable Layotter
     *
     * @return void
     */
    public static function disable_for_post_type($post_type)
    {
        self::$disabled_post_types[] = $post_type;
    }

    /**
     *
     * Explicitly enable Layotter for a post_type
     * Needed to add not public post_types
     *
     * @param mixed $post_type The post_types's slug want to enable Layotter
     *
     * @return void
     */
    public static function enable_for_post_type($post_type)
    {
        self::$enabled_post_types[] = $post_type;
    }

    /**
     * renders an element in layotter
     *
     * @param      $element_html
     * @param null $options
     * @param null $col_options
     * @param null $row_options
     * @param null $post_options
     *
     * @return string rendered view
     *
     */
    public function custom_element_view(
        $element_html,
        $options = null,
        $col_options = null,
        $row_options = null,
        $post_options = null
    ) {

        $view = View::make($this->getCurrentView('element'));

        return $view->with([
            'content'      => $element_html,
            'options'      => $options,
            'col_options'  => $col_options,
            'row_options'  => $row_options,
            'post_options' => $post_options,
        ])->render();
    }


    /**
     * render an element for layotter
     *
     * @param      $elements_html
     * @param      $class
     * @param null $options
     * @param null $row_options
     * @param null $post_options
     *
     * @return string rendered view
     */
    public function custom_column_view(
        $elements_html,
        $class,
        $options = null,
        $row_options = null,
        $post_options = null
    ) {

        $view = View::make($this->getCurrentView('column'));

        return $view->with([
            'content'      => $elements_html,
            'class'        => trim($class),
            'options'      => $options,
            'row_options'  => $row_options,
            'post_options' => $post_options,
        ])->render();
    }

    /**
     * renders a row for layotter
     *
     * @param      $cols_html
     * @param      $options
     * @param null $post_options
     *
     * @return string
     */
    public function custom_row_view($cols_html, $options = [], $post_options = null)
    {

        foreach ($options as &$option) {
            if (is_array($option) && empty($option)) {
                $option = null;
            }
            if (is_array($option) && count($option) == 1) {
                $option = reset($option);
            }
        }

        $view = View::make($this->getCurrentView('row'));

        return $view->with([
            'content'      => $cols_html,
            'options'      => $options,
            'post_options' => $post_options,
        ])->render();
    }


    /**
     * renders entire post for layotter
     *
     * @param $rows_html
     * @param $options
     *
     * @return mixed
     */
    public function custom_post_view($rows_html, $options)
    {

        $view = View::make($this->getCurrentView('post'));

        return $view->with([
            'content' => $rows_html,
            'options' => $options,
        ])->render();
    }

    /**
     *
     * @param $for
     *
     * @return string
     */
    final protected function getCurrentView($for)
    {
        $viewParts   = ['Layotter', $for];
        $layoutPaths = [];

        foreach ($GLOBALS['sloth']->container['view.finder']->getPaths() as $path) {
            $layoutPaths[] = $path . DS . implode(DS, $viewParts);
        }

        $finder = new FoldersTemplateFinder($layoutPaths, ['twig']);

        $queryTemplate = new QueryTemplate($finder);
        $template      = $queryTemplate->findTemplate(null, false);

        $viewParts[] = basename($template, '.twig');
        $viewParts   = array_filter($viewParts);

        return implode('.', $viewParts);

    }

    /**
     * sets layotter related filters
     */
    final public function addFilters()
    {
        add_filter('layotter/enable_example_element', '__return_false');
        add_filter('layotter/enable_default_css', '__return_false');
        add_filter('layotter/enable_element_templates', '__return_true');
        add_filter('layotter/enable_post_layouts', '__return_true');


        add_filter('layotter/enabled_post_types', [$this, 'enabled_post_types']);
        add_filter('layotter/rows/allowed_layouts', [$this, 'allowed_row_layouts']);
        add_filter('layotter/rows/default_layout', [$this, 'default_row_layout']);
        add_filter('layotter/columns/classes', [$this, 'custom_column_classes']);
        #view filters
        add_filter('layotter/view/element', [$this, 'custom_element_view'], 10, 5);
        add_filter('layotter/view/column', [$this, 'custom_column_view'], 10, 5);
        add_filter('layotter/view/row', [$this, 'custom_row_view'], 10, 9);
        add_filter('layotter/view/post', [$this, 'custom_post_view'], 10, 9);
    }
}
