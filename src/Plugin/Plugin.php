<?php

declare(strict_types=1);

namespace Sloth\Plugin;

use Corcel\Model\Menu;
use Corcel\Model\User;
use Sloth\ACF\ACFHelper;
use Sloth\Admin\Customizer;
use Sloth\CarbonFields\CarbonFields;
use Sloth\Facades\Configure;
use Sloth\Facades\Deployment;
use Sloth\Facades\View;
use Sloth\Singleton\Singleton;
use PostTypes\PostType;
use Sloth\Core\Sloth;
use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Media\Version;
use Sloth\Utility\Utility;

use function post_password_required;

/**
 * Main Sloth Plugin class.
 *
 * @since 1.0.0
 */
class Plugin extends Singleton
{
    /**
     * Current theme path.
     *
     * @since 1.0.0
     * @var string|null
     */
    public ?string $current_theme_path = null;

    /**
     * Application container.
     *
     * @since 1.0.0
     * @var mixed
     */
    private mixed $container;

    /**
     * Registered modules.
     *
     * @since 1.0.0
     * @var array<int, string>
     */
    private array $modules = [];

    /**
     * Registered models.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    private array $models = [];

    /**
     * Registered taxonomies.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    private array $taxonomies = [];

    /**
     * Current model instance.
     *
     * @since 1.0.0
     * @var mixed
     */
    private mixed $currentModel;

    /**
     * Current layout.
     *
     * @since 1.0.0
     * @var string|null
     */
    private ?string $currentLayout = null;

    /**
     * Template context.
     *
     * @since 1.0.0
     * @var array<string, mixed>|null
     */
    private ?array $context = null;

    /**
     * Plugin constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        if (!is_blog_installed()) {
            return;
        }

        $this->container = $GLOBALS['sloth']->container;
        $this->loadControllers();
        $this->fixPagination();

        $this->current_theme_path = realpath((string) get_template_directory());
        $this->container->addPath('theme', (string) $this->current_theme_path);

        if (is_dir($this->current_theme_path . DS . 'View')) {
            $this->container['view.finder']->addLocation($this->current_theme_path . DS . 'View');
        }

        $this->container['view.finder']->addLocation(dirname(__DIR__) . DS . '_view');
        $this->container['twig.loader']->setPaths($this->container['view.finder']->getPaths());

        $themeConfig = $this->current_theme_path . DS . 'config.php';
        if (file_exists($themeConfig)) {
            include_once $themeConfig;
        }
        $this->setDefaultConfig();
        $this->addFilters();
    }

    /**
     * Set default configuration.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function setDefaultConfig(): void
    {
        Configure::write('layotter_prepare_fields', 2);
    }

    /**
     * Load all controllers.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function loadControllers(): void
    {
        foreach (glob(\get_template_directory() . DS . 'Controller' . DS . '*Controller.php') as $file) {
            include($file);
        }
    }

    /**
     * Load a class from a file.
     *
     * @since 1.0.0
     *
     * @param string $file File path
     *
     * @return string Class name
     */
    protected function loadClassFromFile(string $file): string
    {
        $filename = basename($file, '.php');
        $namespace = 'App\\' . str_replace(DIR_APP, '', dirname($file));
        $namespace = str_replace(DS, '\\', $namespace);
        $namespace = rtrim($namespace, '\\');

        $className = $namespace . '\\' . $filename;

        if (class_exists($className)) {
            return $className;
        }

        include_once $file;

        return $className;
    }

    /**
     * Load all models.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadModels(): void
    {
        foreach (glob(DIR_APP . 'Model' . DS . '*.php') as $file) {
            $modelName = $this->loadClassFromFile($file);

            $model = new $modelName();
            if (!$model->register) {
                continue;
            }

            $model->register();

            $this->models[$model->getPostType()] = $modelName;

            if ($model::$layotter !== false) {
                $this->container['layotter']->enable_for_post_type($model->getPostType());
                if (is_array($model::$layotter) && isset($model::$layotter['allowed_row_layouts'])) {
                    $this->container['layotter']->set_layouts_for_post_type($model->getPostType(), $model::$layotter['allowed_row_layouts']);
                }
            } else {
                $this->container['layotter']->disable_for_post_type($model->getPostType());
            }
            \flush_rewrite_rules(true);
        }
    }

    /**
     * Load all taxonomies.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadTaxonomies(): void
    {
        foreach (glob(DIR_APP . 'Taxonomy' . DS . '*.php') as $file) {
            $taxonomyName = $this->loadClassFromFile($file);
            $taxonomy = new $taxonomyName();
            if (method_exists($taxonomy, 'register')) {
                $taxonomy->register();
            }
            $this->taxonomies[$taxonomy->getTaxonomy()] = $taxonomyName;
        }
    }

    /**
     * Load all API controllers.
     *
     * @since 1.0.0
     *
     * @return void
     * @throws \Exception
     */
    public function loadApiControllers(): void
    {
        foreach (glob(DIR_APP . 'Api' . DS . '*.php') as $file) {
            $controllerName = $this->loadClassFromFile($file);

            $controller = new $controllerName();

            if (!is_subclass_of($controller, 'Sloth\Api\Controller')) {
                throw new \Exception('ApiController needs to extend Sloth\Api\Controller');
            }

            $methods = get_class_methods($controller);
            $routePrefix = Utility::viewize((new \ReflectionClass($controller))->getShortName());
            $routes = [];

            foreach ($methods as $method) {
                if (str_starts_with($method, '_') || $method === 'single') {
                    continue;
                }
                $routes[$routePrefix . '/' . Utility::viewize($method) . '(?:/(?P<id>\w+))?'] = $method;
            }

            if (method_exists($controller, 'single')) {
                $routes[$routePrefix] = 'index';
                $routes[$routePrefix . '(?:/(?P<id>[a-z0-9.-]+))?'] = 'single';
            } else {
                $routes[$routePrefix . '(?:/(?P<id>[a-z0-9.-]+))?'] = 'index';
            }

            foreach ($routes as $route => $action) {
                add_action('rest_api_init', function () use ($route, $action, $controller): void {
                    register_rest_route(
                        'sloth/v1',
                        '/' . $route,
                        [
                            'methods'  => ['GET', 'POST', 'DELETE', 'PUT'],
                            'callback' => function ($request) use ($controller, $action): \WP_REST_Response {
                                $controller->setRequest($request);
                                $param = $request->get_url_params('id');
                                $data = call_user_func_array([$controller, $action], [reset($param)]);

                                return new \WP_REST_Response(
                                    $data,
                                    $controller->response->status,
                                    $controller->response->headers
                                );
                            },
                        ]
                    );
                });
            }
        }
    }

    /**
     * Load all modules.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadModules(): void
    {
        foreach (glob((string) get_template_directory() . DS . 'Module' . DS . '*Module.php') as $file) {
            $moduleName = $this->loadClassFromFile($file);

            if (is_array($moduleName::$layotter) && class_exists('\\Layotter')) {
                $className = substr(strrchr($moduleName, "\\"), 1);

                $moduleClassName = $moduleName;
                eval("class $className extends \\Sloth\\Module\\LayotterElement {
					static \$module = '$moduleClassName';
				}");
                \Layotter::register_element(strtolower(substr(strrchr($moduleName, "\\"), 1)), $className);
            }

            if ($moduleName::$json) {
                $m = new $moduleName();
                add_action('wp_ajax_nopriv_' . $m->getAjaxAction(), [new $moduleName(), 'getJSON']);
                add_action('wp_ajax_' . $m->getAjaxAction(), [new $moduleName(), 'getJSON']);

                $route = [Utility::viewize(Utility::normalize(class_basename($m)))];
                if (is_array($moduleName::$json) && isset($moduleName::$json['params'])) {
                    foreach ($moduleName::$json['params'] as $param) {
                        $route[] = '(?P<' . $param . '>[a-z0-9-]+)';
                    }
                }

                add_action('rest_api_init', function () use ($route, $m): void {
                    register_rest_route(
                        'sloth/v1/module',
                        '/' . implode('/', $route),
                        [
                            'methods'  => ['GET', 'POST'],
                            'callback' => function (\WP_REST_Request $request) use ($m): void {
                                $m->getJSON($request->get_params());
                            },
                        ]
                    );
                });
                unset($m);
            }

            $this->modules[] = $moduleName;
        }
    }

    /**
     * Add WordPress filters and actions.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function addFilters(): void
    {
        ACFHelper::getInstance();
        Deployment::getInstance()->boot();

        add_action('pre_get_posts', function ($query) {
            if (!is_admin() && !defined('REST_REQUEST')) {
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
        Customizer::getInstance()->boot();

        add_filter('network_admin_url', [$this, 'fixNetworkAdminUrl']);
        add_action('init', [$this, 'loadApiControllers'], 20);
        add_action('init', [$this, 'loadModels'], 20);
        add_action('init', [$this, 'loadTaxonomies'], 20);
        add_action('init', [$this, 'loadModules'], 20);
        add_action('init', [$this, 'registerMenus'], 20);
        add_action('init', [$this, 'initModels'], 20);
        add_action('init', [$this, 'loadAppIncludes'], 20);
        add_action('init', [$this, 'registerImageSizes'], 20);
        add_action('init', [$this, 'autoloadPlugins'], 20);
        add_action('init', [$this, 'registerNavMenus'], 20);
        add_action('admin_menu', [$this, 'initTaxonomies'], 20);
        add_action('save_post', [$this, 'trackDataChange'], 20);
        add_action('admin_menu', [$this, 'cleanupAdminMenu'], 20);

        add_action('admin_head', function (): void {
            echo '<style>
.layotter-preview { border-collapse: collapse; }
.layotter-preview th, .layotter-preview td { text-align: left !important; vertical-align: top; }
.layotter-preview th { padding-right: 10px; }
.layotter-preview tr:nth-child(even), .layotter-preview tr:nth-child(even) { background: #eee; }
td.media-icon img[src$=".svg"], img[src$=".svg"].attachment-post-thumbnail { width: 100% !important; height: auto !important; }
.media-icon img[src$=".svg"] { width: 60px; }
</style>';
        });

        add_action('template_redirect', [$this, 'getTemplate'], 20);

        if ((bool) getenv('FORCE_SSL')) {
            add_action('template_redirect', [$this, 'forceSsl'], 30);
        }

        add_filter('upload_mimes', function (array $mimes): array {
            $mimes['svg'] = 'image/svg+xml';

            return $mimes;
        });

        if (Configure::read('wp-json.baseUrl')) {
            add_filter('rest_url_prefix', fn(): string => (string) Configure::read('wp-json.baseUrl'));
        }

        if (Configure::read('core.hide_updates')) {
            add_filter('pre_site_transient_update_core', [$this, 'hideUpdates']);
        }
        if (Configure::read('plugins.hide_updates')) {
            add_filter('pre_site_transient_update_plugins', [$this, 'hideUpdates']);
        }
        if (Configure::read('themes.hide_updates')) {
            add_filter('pre_site_transient_update_themes', [$this, 'hideUpdates']);
        }

        $this->container['layotter']->addFilters();
    }

    /**
     * Remove unnecessary WordPress references from wp_head.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function obfuscateWP(): void
    {
        add_action('wp_print_styles', function (): void {
            wp_dequeue_style('wp-block-library');
        }, 100);
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'index_rel_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('wp_head', 'start_post_rel_link');
        remove_action('wp_head', 'parent_post_rel_link');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        remove_action('wp_head', 'locale_stylesheet');
        remove_action('wp_head', 'rel_canonical');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head', 10);
        remove_action('wp_head', 'wp_oembed_add_discovery_links', 10);
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        add_filter('emoji_svg_url', '__return_false');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }

    /**
     * Make all links root-relative.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function makeLinksRelative(): void
    {
        $filters = [
            'day_link', 'year_link', 'post_link', 'page_link', 'term_link',
            'month_link', 'search_link', 'the_permalink', 'get_shortlink',
            'post_type_link', 'get_pagenum_link', 'post_type_archive_link',
            'get_comments_pagenum_link', 'sloth_get_permalink',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, [$this, 'getRelativePermalink'], 90, 1);
        }

        add_filter('the_content', [$this, 'getRelativeHrefs'], 90, 1);
    }

    /**
     * Make all uploads URLs root-relative.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function makeUploadsRelative(): void
    {
        $filters = [
            'wp_get_attachment_url', 'template_directory_uri',
            'attachment_link', 'content_url',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, [$this, 'getRelativePermalink'], 90, 1);
        }

        add_filter('sloth_get_attachment_link', [$this, 'getRelativePermalink'], 90, 1);
        add_filter('the_content', [$this, 'getRelativeSrcs'], 90, 1);
    }

    /**
     * Get a relative permalink.
     *
     * @since 1.0.0
     *
     * @param string $input The full URL
     *
     * @return string
     */
    public function getRelativePermalink(string $input): string
    {
        return (string) parse_url($input, PHP_URL_PATH);
    }

    /**
     * Replace home URL.
     *
     * @since 1.0.0
     *
     * @param string $input The input string
     *
     * @return string
     */
    public function replaceHomeUrl(string $input): string
    {
        return str_replace(trim((string) WP_HOME, '/'), '', $input);
    }

    /**
     * Make hrefs in content relative.
     *
     * @since 1.0.0
     *
     * @param string $input The content
     *
     * @return string
     */
    public function getRelativeHrefs(string $input): string
    {
        return str_replace('href="' . rtrim((string) WP_HOME, '/'), 'href="', $input);
    }

    /**
     * Make srcs in content relative.
     *
     * @since 1.0.0
     *
     * @param string $input The content
     *
     * @return string
     */
    public function getRelativeSrcs(string $input): string
    {
        return str_replace('src="' . rtrim((string) WP_HOME, '/'), 'src="' . rtrim((string) WP_HOME, '/'), $input);
    }

    /**
     * Hide WordPress update notifications.
     *
     * @since 1.0.0
     *
     * @return object
     */
    public function hideUpdates(): object
    {
        global $wpVersion;

        return (object) [
            'last_checked'    => time(),
            'version_checked' => $wpVersion,
        ];
    }

    /**
     * Fix network admin URL.
     *
     * @since 1.0.0
     *
     * @param string $url The URL
     *
     * @return string
     */
    public function fixNetworkAdminUrl(string $url): string
    {
        $urlInfo = parse_url($url);

        if (!preg_match('/^\/cms/', (string) ($urlInfo['path'] ?? ''))) {
            $url = (string) $urlInfo['scheme'] . '://' . $urlInfo['host'] . '/cms' . $urlInfo['path'];
            if (isset($urlInfo['query']) && !empty($urlInfo['query'])) {
                $url .= '?' . $urlInfo['query'];
            }
        }

        return $url;
    }

    /**
     * Force SSL redirect.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function forceSsl(): void
    {
        if ((bool) getenv('FORCE_SSL') && !is_ssl()) {
            wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit();
        }
    }

    /**
     * Get the template context.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        if (is_array($this->context)) {
            return $this->context;
        }

        $this->context = [
            'wp_title' => trim((string) wp_title('', false)),
            'site'     => [
                'url'           => (string) home_url(),
                'rdf'           => (string) get_bloginfo('rdf_url'),
                'rss'           => (string) get_bloginfo('rss_url'),
                'rss2'          => (string) get_bloginfo('rss2_url'),
                'atom'          => (string) get_bloginfo('atom_url'),
                'language'      => get_bloginfo('language'),
                'charset'       => get_bloginfo('charset'),
                'pingback'      => $this->pingback_url = (string) get_bloginfo('pingback_url'),
                'admin_email'   => (string) get_bloginfo('admin_email'),
                'name'          => (string) get_bloginfo('name'),
                'title'         => (string) get_bloginfo('name'),
                'description'   => (string) get_bloginfo('description'),
                'canonical_url' => (string) home_url((string) $_SERVER['REQUEST_URI']),
            ],
            'globals'  => [
                'home_url'   => (string) home_url('/'),
                'theme_url'  => (string) get_template_directory_uri(),
                'images_url' => (string) get_template_directory_uri() . '/assets/img',
            ],
            'sloth'    => [
                'current_layout' => basename($this->currentLayout ?? '', '.twig'),
            ],
        ];

        if (is_single() || is_page()) {
            $qo = get_queried_object();

            if (!isset($this->currentModel)) {
                $a = call_user_func([$this->getModelClass($qo->post_type), 'find'], [$qo->ID]);
                $this->currentModel = $a->first();
            }
            $this->context['post'] = $this->currentModel;
            $this->context[$qo->post_type] = $this->currentModel;
        }

        if (is_tax()) {
            global $taxonomy;
            if (!isset($this->currentModel)) {
                $a = call_user_func([$this->getTaxonomyClass($taxonomy), 'find'], [get_queried_object()->term_id]);
                $this->currentModel = $a->first();
            }
            $this->context['taxonomy'] = $this->currentModel;
            $this->context[$taxonomy] = $this->currentModel;
        }

        if (is_author()) {
            if (!isset($this->currentModel)) {
                $this->currentModel = User::find(\get_queried_object()->id);
            }
            $this->context['user'] = $this->currentModel;
            $this->context['author'] = $this->currentModel;
        }

        return $this->context;
    }

    /**
     * Get and render the template.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function getTemplate(): void
    {
        $template = null;
        $this->fixPagination();

        if (!is_dir($this->current_theme_path . DS . 'View' . DS . 'Layout')) {
            return;
        }

        global $post;
        $post = is_object($post) ? $post : new \StdClass();

        if (Configure::read('theme.routes') && is_array(Configure::read('theme.routes'))) {
            $uri = (string) $_SERVER['REQUEST_URI'];

            if (false !== $pos = strpos($uri, '?')) {
                $uri = substr($uri, 0, $pos);
            }
            $uri = rtrim(rawurldecode($uri), '/');

            $routes = Configure::read('theme.routes');

            if (isset($routes[$uri])) {
                $template = basename($routes[$uri]['Layout'], '.twig');
                if (isset($routes[$uri]['ContentType'])) {
                    header('Content-Type: ' . $routes[$uri]['ContentType']);
                }
            }
        }

        if ($template === null) {
            $layoutPaths = [];
            foreach ($this->container['view.finder']->getPaths() as $path) {
                $layoutPaths[] = $path . DS . 'Layout';
            }
            $finder = new ByFolders($layoutPaths, 'twig');
            $queryTemplate = new QueryTemplate($finder);
            $template = $queryTemplate->findTemplate(null, false);
        }

        if ($template === '') {
            if ($this->isDevEnv()) {
                $ext = pathinfo((string) $_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
                    preg_match('/(.+)-([0-9]+)x([0-9]+)\.(jpg|jpeg|png|gif)$/', (string) $_SERVER['REQUEST_URI'], $matches);

                    $w = $matches[2] ?? 1024;
                    $h = $matches[3] ?? 768;

                    header('Location: https://placebeard.it/' . $w . '/' . $h);
                }

                if (pathinfo((string) $_SERVER['REQUEST_URI'], PATHINFO_EXTENSION) === 'svg') {
                    header('Location: http://placeholder.pics/svg/300/DEDEDE/555555/SVG');
                }
            }

            return;
        }

        if (post_password_required()) {
            $template = 'password-form';
        }

        $this->currentLayout = (string) $template;

        $viewName = basename((string) $template, '.twig');

        $ext = pathinfo((string) $_SERVER['REQUEST_URI'], PATHINFO_EXTENSION);
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'], true)) {
            new Version((string) $_SERVER['REQUEST_URI']);
        }

        $view = View::make('Layout.' . $viewName);

        echo $view->with($this->getContext())->render();
        die();
    }

    /**
     * Register menus.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerMenus(): void
    {
        $menus = Configure::read('theme.menus');
        if ($menus && is_array($menus)) {
            foreach ($menus as $menu => $title) {
                \register_nav_menu($menu, __($title));
            }
        }
    }

    /**
     * Register image sizes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function registerImageSizes(): void
    {
        $imageSizes = Configure::read('theme.image-sizes');
        if ($imageSizes && is_array($imageSizes)) {
            foreach ($imageSizes as $name => $options) {
                $options = array_merge([
                    'width'   => 800,
                    'height'  => 600,
                    'crop'    => false,
                    'upscale' => false,
                ], $options);
                \add_image_size($name, $options['width'], $options['height'], $options['crop']);
            }
        }
    }

    /**
     * Autoload plugins.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function autoloadPlugins(): void
    {
        if (!Configure::read('plugins.autoactivate')) {
            return;
        }

        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        foreach (array_keys(\get_plugins()) as $plugin) {
            if (is_plugin_active($plugin)) {
                continue;
            }
            $pi = pathinfo($plugin);
            if (in_array($pi['dirname'], (array) Configure::read('plugins.autoactivate.blacklist'), true)) {
                continue;
            }

            $plugins = \get_option('active_plugins');
            array_push($plugins, $plugin);
            \update_option('active_plugins', $plugins);
        }
    }

    /**
     * Fix pagination.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected function fixPagination(): void
    {
        if (isset($_GET['page'])) {
            $currentPage = (int) $_GET['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn() => $currentPage);
        }

        global $wpQuery;
        if (isset($wpQuery->query['page'])) {
            $currentPage = (int) $wpQuery->query['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn() => $currentPage);
        }

        if (isset($wpQuery->query['paged'])) {
            $currentPage = (int) $wpQuery->query['paged'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn() => $currentPage);
        }
    }

    /**
     * Initialize models.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function initModels(): void
    {
        foreach ($this->models as $v) {
            $model = new $v();
            $model->init();
            unset($model);
        }
    }

    /**
     * Initialize taxonomies.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function initTaxonomies(): void
    {
        foreach ($this->taxonomies as $v) {
            $tax = new $v();
            $tax->init();
            unset($tax);
        }
    }

    /**
     * Load app includes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function loadAppIncludes(): void
    {
        add_filter('post_type_archive_link', function ($link, $post_type) {
            if ($post_type === 'post') {
                $pto = get_post_type_object($post_type);
                if (is_string($pto->has_archive)) {
                    $link = trailingslashit((string) home_url($pto->has_archive));
                }
            }

            return $link;
        }, 2, 10);

        $dirAppIncludes = DIR_APP . DS . 'Includes' . DS;

        if (!is_dir($dirAppIncludes)) {
            return;
        }

        $filesInclude = glob($dirAppIncludes . '*.php');
        if (!count($filesInclude)) {
            return;
        }

        foreach ($filesInclude as $file) {
            include_once realpath($file);
        }
    }

    /**
     * Fix routes.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function fixRoutes(): void
    {
        $routes = Configure::read('theme.routes');
        if ($routes && is_array($routes)) {
            foreach ($routes as $route => $action) {
                $regex = trim($route, '/');

                add_action('init', function () use ($regex): void {
                    add_rewrite_tag('%is_some_other_route%', '(\d)');
                    add_rewrite_rule($regex, 'index.php?is_some_other_route=1', 'top');
                    flush_rewrite_rules();
                });
            }
        }
    }

    /**
     * Get model class name.
     *
     * @since 1.0.0
     *
     * @param string $key Post type key
     *
     * @return string
     */
    public function getModelClass(string $key = ''): string
    {
        return $this->models[$key] ?? '\Sloth\Model\Post';
    }

    /**
     * Get all models.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function getAllModels(): array
    {
        return $this->models;
    }

    /**
     * Get taxonomy class name.
     *
     * @since 1.0.0
     *
     * @param string $key Taxonomy key
     *
     * @return string
     */
    public function getTaxonomyClass(string $key = ''): string
    {
        return $this->taxonomies[$key] ?? '\Sloth\Model\Taxonomy';
    }

    /**
     * Get all taxonomies.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function getAllTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Get current template.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function getCurrentTemplate(): ?string
    {
        return $this->currentLayout;
    }

    /**
     * Get current layout.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function getCurrentLayout(): ?string
    {
        return $this->currentLayout;
    }

    /**
     * Track data changes for development.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function trackDataChange(): bool
    {
        if (!$this->isDevEnv()) {
            return false;
        }
        file_put_contents(DIR_CACHE . DS . 'reload', (string) time());

        return true;
    }

    /**
     * Get post type class.
     *
     * @since 1.0.0
     *
     * @param string $postType Post type
     *
     * @return string
     */
    public function getPostTypeClass(string $postType): string
    {
        return $this->models[$postType] ?? 'Sloth\Model\Post';
    }

    /**
     * Check if in development environment.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isDevEnv(): bool
    {
        return in_array(WP_ENV ?? '', ['development', 'develop', 'dev'], true);
    }

    /**
     * Clean up admin menu.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function cleanupAdminMenu(): void
    {
        global $menu;
        $used = [];
        foreach ($menu as $offset => $menuItem) {
            $pi = pathinfo($menuItem[2], PATHINFO_EXTENSION);
            if (!preg_match('/^php/', $pi)) {
                continue;
            }
            if (in_array($menuItem[2], $used, true)) {
                unset($menu[$offset]);
                continue;
            }
            $used[] = $menuItem[2];
        }
    }

    /**
     * Check if this is a REST API request.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isRest(): bool
    {
        $bIsRest = false;
        if (function_exists('rest_url') && !empty($_SERVER['REQUEST_URI'])) {
            $sRestUrlBase = (string) get_rest_url(get_current_blog_id(), '/');
            $sRestPath = trim(parse_url($sRestUrlBase, PHP_URL_PATH), '/');
            $sRequestPath = trim($_SERVER['REQUEST_URI'], '/');
            $bIsRest = str_starts_with($sRequestPath, $sRestPath);
        }

        return $bIsRest;
    }

    /**
     * Register navigation menus.
     *
     * @since 1.0.0
     *
     * @return void
     * @throws \Exception
     */
    public function registerNavMenus(): void
    {
        if (Configure::read('theme.menus')) {
            if (!is_array(Configure::read('theme.menus'))) {
                throw new \Exception('theme.menus must be an array!');
            }
            foreach (Configure::read('theme.menus') as $location => $name) {
                \register_nav_menu($location, (string) $name);
            }
        }
    }
}
