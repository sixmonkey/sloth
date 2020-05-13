<?php

namespace Sloth\Plugin;

use Corcel\Model\Menu;
use Corcel\Model\User;
use mysql_xdevapi\Exception;
use Sloth\ACF\ACFHelper;
use Sloth\Admin\Customizer;
use Sloth\CarbonFields\CarbonFields;
use Sloth\Facades\Configure;
use Sloth\Facades\Deployment;
use Sloth\Facades\View;

use PostTypes\PostType;

use Sloth\Core\Sloth;

use Brain\Hierarchy\Finder\FoldersTemplateFinder;
use \Brain\Hierarchy\QueryTemplate;
use Sloth\Media\Version;
use Sloth\Utility\Utility;

class Plugin extends \Singleton
{
    public $current_theme_path;
    private $container;
    private $modules = [];
    private $models = [];
    private $taxonomies = [];
    private $currentModel;
    private $currentLayout;
    private $context;

    public function __construct()
    {
        if ( ! is_blog_installed()) {
            return;
        }

        $this->container = $GLOBALS['sloth']->container;
        $this->loadControllers();
        #$this->loadTaxonomies();
        #\Route::instance()->boot();

        $this->fixPagination();

        /**
         * set current_theme_path
         */
        $this->current_theme_path = realpath(get_template_directory());
        /**
         * tell container about current theme path
         */
        $this->container->addPath('theme', $this->current_theme_path);

        /**
         * tell ViewFinder about current theme's view path
         */

        if (is_dir($this->current_theme_path . DS . 'View')) {
            $this->container['view.finder']->addLocation($this->current_theme_path . DS . 'View');
        }

        /**
         * tell ViewFinder about sloths's view path
         */

        $this->container['view.finder']->addLocation(dirname(__DIR__) . DS . '_view');

        /*
		 * Update Twig Loaded registered paths.
		 */
        $this->container['twig.loader']->setPaths($this->container['view.finder']->getPaths());

        /*
		 * include theme's config
		 */
        $theme_config = $this->current_theme_path . DS . 'config.php';
        if (file_exists($theme_config)) {
            include_once $theme_config;
        }
        $this->setDefaultConfig();
        $this->addFilters();
    }

    protected function setDefaultConfig()
    {
        Configure::write('layotter_prepare_fields', 2);
    }

    private function loadControllers()
    {
        foreach (glob(\get_template_directory() . DS . 'Controller' . DS . '*Controller.php') as $file) {
            include($file);
        }
    }

    public function loadModels()
    {

        foreach (glob(DIR_APP . 'Model' . DS . '*.php') as $file) {
            $model_name = 'App\Model\\' . basename($file, '.php');
            if ( ! class_exists($model_name)) {
                include($file);
                $classes    = get_declared_classes();
                $model_name = array_pop($classes);
            }

            $model = new $model_name;
            if ( ! $model->register) {
                continue;
            }

            $post_type = $model->getPostType();
            $model->register();

            $this->models[$post_type] = $model_name;

            if ($model::$layotter !== false) {
                $this->container['layotter']->enable_for_post_type($post_type);
                if (is_array($model::$layotter)) {
                    isset($model::$layotter['allowed_row_layouts']) ? $this->container['layotter']->set_layouts_for_post_type($post_type,
                        $model::$layotter['allowed_row_layouts']) : null;
                }
            } else {
                $this->container['layotter']->disable_for_post_type($post_type);
            }
            \flush_rewrite_rules(true);
        }
    }

    public function loadTaxonomies()
    {
        foreach (glob(DIR_APP . 'Taxonomy' . DS . '*.php') as $file) {
            include($file);
            $classes       = get_declared_classes();
            $taxonomy_name = array_pop($classes);

            $taxonomy = new $taxonomy_name;
            if (method_exists($taxonomy, 'register')) {
                $taxonomy->register();
            }
            $this->taxonomies[$taxonomy->getTaxonomy()] = $taxonomy_name;
        }
    }

    public function loadApiControllers()
    {
        foreach (glob(DIR_APP . 'Api' . DS . '*.php') as $file) {
            include($file);
            $classes         = get_declared_classes();
            $controller_name = array_pop($classes);

            $controller = new $controller_name;

            if ( ! is_subclass_of($controller, 'Sloth\Api\Controller')) {
                throw new Exception('ApiController needs to extend Sloth\Api\Controller');
            }

            $methods      = get_class_methods($controller);
            $route_prefix = Utility::viewize((new \ReflectionClass($controller))->getShortName());
            $routes       = [];

            foreach ($methods as $method) {
                if (substr($method, 0, 1) === '_' || $method === 'single') {
                    continue;
                }
                $routes[$route_prefix . '/' . Utility::viewize($method) . '(?:/(?P<id>\w+))?'] = $method;
            }

            if (method_exists($controller, 'single')) {
                $routes[$route_prefix]                               = 'index';
                $routes[$route_prefix . '(?:/(?P<id>[a-z0-9.-]+))?'] = 'single';
            } else {
                $routes[$route_prefix . '(?:/(?P<id>[a-z0-9.-]+))?'] = 'index';
            }
            foreach ($routes as $route => $action) {
                add_action('rest_api_init',
                    function () use ($route, $action, $controller) {
                        register_rest_route(
                            'sloth/v1',
                            '/' . $route,
                            [
                                'methods'  => ['GET', 'POST', 'DELETE', 'PUT'],
                                'callback' => function ($request) use ($controller, $action) {
                                    $controller->setRequest($request);
                                    $param = $request->get_url_params('id');
                                    $data  = call_user_func_array([$controller, $action], [reset($param)]);

                                    return new \WP_REST_Response($data,
                                        $controller->response->status,
                                        $controller->response->headers);
                                },
                            ]);
                    });
            }

        }
    }

    public function loadModules()
    {
        foreach (glob(get_template_directory() . DS . 'Module' . DS . '*Module.php') as $file) {
            include($file);
            $classes     = get_declared_classes();
            $module_name = array_pop($classes);

            if (is_array($module_name::$layotter) && class_exists('\Layotter')) {

                $class_name = substr(strrchr($module_name, "\\"), 1);

                eval(
                "class $class_name extends \Sloth\Module\LayotterElement {
					static \$module = '$module_name';
				}"
                );
                \Layotter::register_element(strtolower(substr(strrchr($module_name, "\\"), 1)), $class_name);
            }
            if ($module_name::$json) {
                $m = new $module_name;
                #$reflect = new ReflectionClass($object);
                add_action('wp_ajax_nopriv_' . $m->getAjaxAction(),
                    [new $module_name, 'getJSON']);
                add_action('wp_ajax_' . $m->getAjaxAction(),
                    [new $module_name, 'getJSON']);

                $route = [Utility::viewize(Utility::normalize(class_basename($m)))];
                if (is_array($module_name::$json) && isset($module_name::$json['params'])) {
                    foreach ($module_name::$json['params'] as $param) {
                        $route[] = '(?P<' . $param . '>[a-z0-9-]+)';
                    }
                }

                add_action('rest_api_init',
                    function () use ($route, $m) {
                        register_rest_route(
                            'sloth/v1/module',
                            '/' . implode('/', $route),
                            [
                                'methods'  => ['GET', 'POST'],
                                'callback' => function (\WP_REST_Request $request) use ($m) {
                                    $m->getJSON($request->get_params());
                                },
                            ]);
                    });
                unset($m);
            }

            $this->modules[] = $module_name;

        }
    }


    private function addFilters()
    {
        ACFHelper::getInstance();
        \Deployment::instance()->boot();

        /* @TODO: hacky pagination fix! */
        add_action('pre_get_posts',
            function ($query) {
                if ( ! defined('REST_REQUEST')) {
                    $query->set('posts_per_page', -1);
                }

                return $query;
            });


        $this->fixRoutes();
        if (Configure::read('urls.relative')) {
            $this->makeUploadsRelative();
            $this->makeLinksRelative();
        }

        if (Configure::read('links.urls.relative')) {
            $this->makeLinksRelative();
        }

        if (Configure::read('uploads.urls.relative')) {
            $this->makeUploadsRelative();
        }

        if ($this->isDevEnv()) {
            remove_filter('template_redirect', 'redirect_canonical');
        }

        $this->obfuscateWP();

        Customizer::instance()->boot();

        add_filter('network_admin_url', [$this, 'fix_network_admin_url']);
        add_action('init', [$this, 'loadApiControllers'], 20);
        add_action('init', [$this, 'loadModels'], 20);
        add_action('init', [$this, 'loadTaxonomies'], 20);
        add_action('init', [$this, 'loadModules'], 20);
        add_action('init', [$this, 'register_menus'], 20);
        add_action('init', [$this, 'initModels'], 20);
        add_action('init', [$this, 'loadAppIncludes'], 20);
        add_action('init', [$this, 'registerImageSizes'], 20);
        add_action('init', [$this, 'autoloadPlugins'], 20);
        add_action('init', [$this, 'registerNavMenus'], 20);

        add_action('admin_menu', [$this, 'initTaxonomies'], 20);

        add_action('save_post', [$this, 'trackDataChange'], 20);

        add_action('admin_menu', [$this, 'cleanup_admin_menu'], 20);

        add_action('admin_menu', [$this, 'cleanup_admin_menu'], 20);

        add_action('admin_head',

            function () {
                echo '<style>
.layotter-preview {
border-collapse: collapse;
}
    .layotter-preview th,
    .layotter-preview td {
    text-align: left !important;
    vertical-align: top;
    }

    .layotter-preview th {
    	padding-right: 10px;
    }

     .layotter-preview tr:nth-child(even),  .layotter-preview tr:nth-child(even) {
     background: #eee;
     }
     
     td.media-icon img[src$=".svg"],
     img[src$=".svg"].attachment-post-thumbnail { 
     	width: 100% !important; height: auto !important; 
     }
     
     .media-icon img[src$=".svg"] {
     	width: 60px;
     }
  </style>';
            });

        /**
         * For now we give up Controllers an Routing
         */
        #add_action( 'init', [ Sloth::getInstance(), 'setRouter' ], 20 );
        # add_action( 'template_redirect', [ Sloth::getInstance(), 'dispatchRouter' ], 20 );

        add_action('template_redirect', [$this, 'getTemplate'], 20);

        if (getenv('FORCE_SSL')) {
            add_action('template_redirect', [$this, 'force_ssl'], 30);
        }

        // Add svg to allowed mime types
        add_filter('upload_mimes',
            function ($mimes) {
                $mimes['svg'] = 'image/svg+xml';

                return $mimes;
            });

        /* @TODO add_filter( 'acf/fields/post_object/result',
         * function ( $title, $post, $field, $post_id ) {
         * debug( $post );
         * },
         * 10,
         * 4 ); */

        // modify api basUrl
        if (Configure::read('wp-json.baseUrl')) {
            add_filter('rest_url_prefix',
                function () {
                    return Configure::read('wp-json.baseUrl');
                });
        }

        if (Configure::read('core.hide_updates')) {
            add_filter('pre_site_transient_update_core',
                [$this, 'hideUpdates']
            );
        }
        if (Configure::read('plugins.hide_updates')) {
            add_filter('pre_site_transient_update_plugins',
                [$this, 'hideUpdates']
            );
        }
        if (Configure::read('themes.hide_updates')) {
            add_filter('pre_site_transient_update_themes',
                [$this, 'hideUpdates']
            );
        }

        $this->container['layotter']->addFilters();
    }

    /**
     * Remove all unnecessary refrences to WordPress from wp_head
     */
    private function obfuscateWP()
    {
        // remove gutenberg styles
        add_action('wp_print_styles',
            function () {
                wp_dequeue_style('wp-block-library');
            },
            100);
        remove_action('wp_head', 'wp_generator'); // meta generator tag
        remove_action('wp_head', 'rsd_link'); // really simple discovery
        remove_action('wp_head', 'feed_links', 2); // posts and comments feed
        remove_action('wp_head', 'index_rel_link'); // index link
        remove_action('wp_head', 'wlwmanifest_link'); // windows live writer manifest
        remove_action('wp_head', 'feed_links_extra', 3); // additional feeds
        remove_action('wp_head', 'start_post_rel_link'); // start link
        remove_action('wp_head', 'parent_post_rel_link'); // prev link
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head'); // prev and next post
        remove_action('wp_head', 'locale_stylesheet'); // localized stylesheet
        remove_action('wp_head', 'rel_canonical'); // canonical url
        remove_action('wp_head', 'wp_shortlink_wp_head'); // short url
        remove_action('wp_head', 'rest_output_link_wp_head', 10); // rest api link
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10); // oembed
        // Remove emoji script
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        add_filter('emoji_svg_url', '__return_false');

        // Remove oEmbed-specific JavaScript from the front-end and back-end.
        remove_action('wp_head', 'wp_oembed_add_host_js');


        // Inline Kommentarstyles aus wp_head schmeissen
        add_action(
            'widgets_init',
            function () {
                global $wp_widget_factory;
                remove_action(
                    'wp_head',
                    [$wp_widget_factory->widgets['WP_Widget_Recent_Comments'], 'recent_comments_style']
                );
            }
        );
    }

    /**
     * Make all Links root relative
     */
    private function makeLinksRelative()
    {
        add_filter('day_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('year_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('post_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('page_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('term_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('month_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('search_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('the_permalink', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('get_shortlink', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('post_type_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('get_pagenum_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('post_type_archive_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('get_comments_pagenum_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('sloth_get_permalink', [$this, 'getRelativePermalink'], 90, 1);

        add_filter('the_content', [$this, 'getRelativeHrefs'], 90, 1);

    }

    /**
     * Make all Uploads root relative
     */
    private function makeUploadsRelative()
    {
        add_filter('wp_get_attachment_url', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('template_directory_uri', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('attachment_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('content_url', [$this, 'getRelativePermalink'], 90, 1);

        add_filter('sloth_get_attachment_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('the_content', [$this, 'getRelativeSrcs'], 90, 1);
    }

    /**
     * Get a relative Permalink
     *
     * @param $input
     *
     * @return string|string[]|null
     */
    public function getRelativePermalink($input)
    {
        return parse_url($input, PHP_URL_PATH);
    }

    /**
     * Get a relative Permalink
     *
     * @param $input
     *
     * @return string|string[]|null
     */
    public function replaceHomeUrl($input)
    {
        return str_replace(trim(WP_HOME, '/'), '', $input);
    }

    /**
     * @param $input
     *
     * @return mixed
     */
    public function getRelativeHrefs($input)
    {
        return str_replace('href="' . rtrim(WP_HOME, '/'), 'href="', $input);
    }

    /**
     * @param $input
     *
     * @return mixed
     */
    public function getRelativeSrcs($input)
    {
        return str_replace('src="' . rtrim(WP_HOME, '/'), 'src="' . rtrim(WP_HOME, '/'), $input);
    }

    public function hideUpdates()
    {
        global $wp_version;

        return (object)[
            'last_checked'    => time(),
            'version_checked' => $wp_version,
        ];
    }

    public function plugin()
    {
        // rewrite the upload directory
        add_filter(
            'upload_dir',
            function ($uploads_array) {
                $fixed_uploads_array = [];
                foreach ($uploads_array as $part => $value) {
                    if (in_array($part, ['path', 'url', 'basedir', 'baseurl'])) {
                        $fixed_uploads_array[$part] = str_replace(WP_PATH . '/..', '', $value);
                    } else {
                        $fixed_uploads_array[$part] = $value;
                    }
                }

                return $fixed_uploads_array;
            }
        );
    }

    public function fix_network_admin_url($url)
    {
        $url_info = parse_url($url);

        if ( ! preg_match('/^\/cms/', $url_info['path'])) {
            $url = $url_info['scheme'] . '://' . $url_info['host'] . '/cms' . $url_info['path'];
            if (isset($url_info['query']) && ! empty($url_info['query'])) {
                $url .= '?' . $url_info['query'];
            }
        }

        return $url;
    }

    public function force_ssl()
    {
        if (getenv('FORCE_SSL') && ! is_ssl()) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit();
        }
    }

    /**
     * @return array
     */
    public function getContext()
    {
        if (is_array($this->context)) {
            return $this->context;
        }
        $this->context = [
            'wp_title' => trim(wp_title('', false)),
            'site'     => [
                'url'           => home_url(),
                'rdf'           => get_bloginfo('rdf_url'),
                'rss'           => get_bloginfo('rss_url'),
                'rss2'          => get_bloginfo('rss2_url'),
                'atom'          => get_bloginfo('atom_url'),
                'language'      => get_bloginfo('language'),
                'charset'       => get_bloginfo('charset'),
                'pingback'      => $this->pingback_url = get_bloginfo('pingback_url'),
                'admin_email'   => get_bloginfo('admin_email'),
                'name'          => get_bloginfo('name'),
                'title'         => get_bloginfo('name'),
                'description'   => get_bloginfo('description'),
                'canonical_url' => home_url($_SERVER['REQUEST_URI']),
            ],
            'globals'  => [
                'home_url'   => home_url('/'),
                'theme_url'  => get_template_directory_uri(),
                'images_url' => get_template_directory_uri() . '/assets/img',
            ],
            'sloth'    => [
                'current_layout' => basename($this->currentLayout, '.twig'),
            ],
        ];

        if (is_single() || is_page()) {

            $qo = get_queried_object();

            if ( ! isset($this->currentModel)) {
                $a                  = call_user_func([$this->getModelClass($qo->post_type), 'find'],
                    [$qo->ID]);
                $this->currentModel = $a->first();
            }
            $this->context['post']         = $this->currentModel;
            $this->context[$qo->post_type] = $this->currentModel;
        }

        if (is_tax()) {
            global $taxonomy;
            if ( ! isset($this->currentModel)) {
                $a                  = call_user_func([$this->getTaxonomyClass($taxonomy), 'find'],
                    [get_queried_object()->term_id]);
                $this->currentModel = $a->first();
            }
            $this->context['taxonomy'] = $this->currentModel;
            $this->context[$taxonomy]  = $this->currentModel;
        }

        if (is_author()) {
            if ( ! isset($this->currentModel)) {
                $this->currentModel = User::find(\get_queried_object()->id);
            }
            $this->context['user']   = $this->currentModel;
            $this->context['author'] = $this->currentModel;
        }

        return $this->context;
    }

    public function getTemplate()
    {
        $template = null;
        $this->fixPagination();
        //@TODO: fix for older themes structure
        if ( ! is_dir($this->current_theme_path . DS . 'View' . DS . 'Layout')) {
            return;
        }
        global $post;

        $post = is_object($post) ? $post : new \StdClass;

        if (Configure::read('theme.routes') && is_array(Configure::read('theme.routes'))) {
            $uri = $_SERVER['REQUEST_URI'];

            // Strip query string (?foo=bar) and decode URI
            if (false !== $pos = strpos($uri, '?')) {
                $uri = substr($uri, 0, $pos);
            }
            # @TODO this fix is ugly
            $uri = rtrim(rawurldecode($uri), '/');

            $routes = Configure::read('theme.routes');

            if (isset($routes[$uri])) {
                $template = basename($routes[$uri]['Layout'], '.twig');
                if (isset($routes[$uri]['ContentType'])) {
                    header('Content-Type: ' . $routes[$uri]['ContentType']);
                }
            }
        }


        // Switch to regular WordPress Templates
        if (is_null($template)) {

            $layoutPaths = [];
            foreach ($this->container['view.finder']->getPaths() as $path) {
                $layoutPaths[] = $path . DS . 'Layout';
            }
            $finder = new FoldersTemplateFinder($layoutPaths, ['twig']);

            $queryTemplate = new QueryTemplate($finder);
            $template      = $queryTemplate->findTemplate();
        }

        if ($template == '') {

            if ($this->isDevEnv()) {
                if (in_array(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION),
                    ['jpg', 'jpeg', 'png', 'gif'])) {

                    preg_match('/(.+)-([0-9]+)x([0-9]+)\.(jpg|jpeg|png|gif)$/', $_SERVER['REQUEST_URI'], $matches);


                    $w = isset($matches[2]) ? $matches[2] : 1024;
                    $h = isset($matches[3]) ? $matches[3] : 768;

                    header('Location: https://placebeard.it/' . $w . '/' . $h);
                }

                if (pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION) == 'svg') {
                    header('Location: http://placeholder.pics/svg/300/DEDEDE/555555/SVG');
                }
            }

            return;
        }

        $this->currentLayout = $template;

        $view_name = basename($template, '.twig');


        if (in_array(pathinfo($_SERVER['REQUEST_URI'], PATHINFO_EXTENSION),
            ['jpg', 'jpeg', 'png', 'gif'])) {
            $mv = new Version($_SERVER['REQUEST_URI']);
        }

        $view = View::make('Layout.' . $view_name);

        echo $view
            ->with(
                $this->getContext()
            )
            ->render();
        die();
    }


    /**
     * register menus for the theme
     */
    public function register_menus()
    {
        $menus = Configure::read('theme.menus');
        if ($menus && is_array($menus)) {
            foreach ($menus as $menu => $title) {
                \register_nav_menu($menu, __($title));
            }
        }
    }

    /**
     * register image sizes configured in theme.image-sizes
     */
    public function registerImageSizes()
    {
        $image_sizes = Configure::read('theme.image-sizes');
        if ($image_sizes && is_array($image_sizes)) {
            foreach ($image_sizes as $name => $options) {
                $options = array_merge([
                    'width'   => 800,
                    'height'  => 600,
                    'crop'    => false,
                    'upscale' => false,
                ],
                    $options);
                \add_image_size($name, $options['width'], $options['height'], $options['crop']);
            }
        }
    }

    public function autoloadPlugins()
    {


        if ( ! Configure::read('plugins.autoactivate')) {
            return;
        }

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        foreach (array_keys(\get_plugins()) as $plugin) {
            // bail if plugin is already active
            if (is_plugin_active($plugin)) {
                continue;
            }
            // bail if plugin is blacklisted
            $pi = pathinfo($plugin);
            if (in_array($pi['dirname'], Configure::read('plugins.autoactivate.blacklist'))) {
                continue;
            }

            $plugins = \get_option('active_plugins');
            array_push($plugins, $plugin);
            \update_option('active_plugins', $plugins);
        }
    }

    protected function fixPagination()
    {
        /**
         * hand current page from get to Illuminate
         */
        if (isset($_GET['page'])) {
            $currentPage = $_GET['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($currentPage) {
                return $currentPage;
            });
        }
        global $wp_query;
        /**
         * hand current page from wp_query to Illuminate
         */
        if (isset($wp_query->query['page'])) {
            $currentPage = $wp_query->query['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($currentPage) {
                return $currentPage;
            });
        }

        if (isset($wp_query->query['paged'])) {
            $currentPage = $wp_query->query['paged'];
            \Illuminate\Pagination\Paginator::currentPageResolver(function () use ($currentPage) {
                return $currentPage;
            });
        }
    }

    public function initModels()
    {
        foreach ($this->models as $k => $v) {
            $model = new $v;
            $model->init();
            unset($model);
        }
    }

    public function initTaxonomies()
    {
        foreach ($this->taxonomies as $k => $v) {
            $tax = new $v;
            $tax->init();
            unset($tax);
        }
    }

    public function loadAppIncludes()
    {
        add_filter('post_type_archive_link',
            function ($link, $post_type) {
                if ($post_type == 'post') {
                    $pto = get_post_type_object($post_type);
                    if (is_string($pto->has_archive)) {
                        $link = trailingslashit(home_url($pto->has_archive));
                    }
                }

                return $link;
            },
            2,
            10);

        $dir_app_includes = (DIR_APP . DS . 'Includes' . DS);

        if ( ! is_dir($dir_app_includes)) {
            return false;
        }

        $files_include = glob($dir_app_includes . '*.php');
        if ( ! count($files_include)) {
            return false;
        }

        foreach ($files_include as $file) {
            include_once realpath($file);
        }
    }

    public function fixRoutes()
    {
        $routes = Configure::read('theme.routes');
        if ($routes && is_array($routes)) {
            foreach ($routes as $route => $action) {
                $regex = trim($route, '/');

                // Add the rewrite rule to the top
                add_action('init',
                    function () use ($regex) {
                        add_rewrite_tag('%is_some_other_route%', '(\d)');
                        add_rewrite_rule($regex, 'index.php?is_some_other_route=1', 'top');
                        flush_rewrite_rules();
                    });
            }
        }
    }

    public function getModelClass($key = '')
    {
        return isset($this->models[$key]) ? $this->models[$key] : '\Sloth\Model\Post';
    }

    public function getAllModels()
    {
        return $this->models;
    }

    public function getTaxonomyClass($key = '')
    {
        return isset($this->taxonomies[$key]) ? $this->taxonomies[$key] : '\Sloth\Model\Taxonomy';
    }

    public function getAllTaxonomies()
    {
        return $this->taxonomies;
    }

    public function getCurrentTemplate()
    {
        return $this->currentLayout;
    }

    public function getCurrentLayout()
    {
        return $this->currentLayout;
    }

    public function trackDataChange()
    {
        if ( ! $this->isDevEnv()) {
            return false;
        }
        file_put_contents(DIR_CACHE . DS . 'reload', time());
    }

    public function getPostTypeClass($post_type)
    {
        return isset($this->models[$post_type]) ? $this->models[$post_type] : 'Sloth\Model\Post';
    }

    public function isDevEnv()
    {
        return in_array(WP_ENV, ['development', 'develop', 'dev']);
    }


    public function cleanup_admin_menu()
    {
        global $menu;
        $used = [];
        foreach ($menu as $offset => $menu_item) {
            $pi = pathinfo($menu_item[2], PATHINFO_EXTENSION);
            if ( ! preg_match('/^php/', $pi)) {
                continue;
            }
            if (in_array($menu_item[2], $used)) {
                unset($menu[$offset]);
                continue;
            }
            $used[] = $menu_item[2];


        }
    }

    /**
     * Checks if the current request is a WP REST API request.
     *
     * Case #1: After WP_REST_Request initialisation
     * Case #2: Support "plain" permalink settings
     * Case #3: URL Path begins with wp-json/ (your REST prefix)
     *          Also supports WP installations in subfolders
     *
     * @returns boolean
     * @author matzeeable
     */
    function is_rest()
    {
        $bIsRest = false;
        if (function_exists('rest_url') && ! empty($_SERVER['REQUEST_URI'])) {
            $sRestUrlBase = get_rest_url(get_current_blog_id(), '/');
            $sRestPath    = trim(parse_url($sRestUrlBase, PHP_URL_PATH), '/');
            $sRequestPath = trim($_SERVER['REQUEST_URI'], '/');
            $bIsRest      = (strpos($sRequestPath, $sRestPath) === 0);
        }

        return $bIsRest;
    }

    /**
     * register menus
     *
     * @throws \Exception
     */
    public function registerNavMenus()
    {
        if (Configure::read('theme.menus')) {
            if ( ! is_array(Configure::read('theme.menus'))) {
                throw new \Exception('theme.menus must be an array!');
            }
            foreach (Configure::read('theme.menus') as $location => $name) {
                \register_nav_menu($location, $name);
            }
        }
    }
}
