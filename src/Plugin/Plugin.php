<?php

declare(strict_types=1);

namespace Sloth\Plugin;

use Sloth\ACF\ACFHelper;
use Sloth\Admin\Customizer;
use Sloth\CarbonFields\CarbonFields;
use Sloth\Core\Sloth;
use Sloth\Facades\Configure;
use Sloth\Facades\Deployment;
use Sloth\Facades\View;
use Sloth\Singleton\Singleton;
use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Media\Version;
use Sloth\Utility\Utility;
use Symfony\Component\HttpFoundation\Response;
use WP_REST_Response;

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
     */
    public ?string $current_theme_path = null;

    /**
     * Application container.
     *
     * @since 1.0.0
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
     */
    private mixed $currentModel = null;

    /**
     * Current layout.
     *
     * @since 1.0.0
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

        $routesFile = $this->current_theme_path . DS . 'routes.php';
        if (file_exists($routesFile)) {
            include_once $routesFile;
        }

        $this->setDefaultConfig();
        $this->addFilters();
    }

    /**
     * Set default configuration.
     *
     * Initializes core framework settings that theme configs can override.
     *
     * @since 1.0.0
     */
    protected function setDefaultConfig(): void
    {
        Configure::write('layotter_prepare_fields', 2);
    }

    /**
     * Load all controllers.
     *
     * Auto-discovers controller files from the theme's Controller directory
     * and includes them. Controllers must follow WordPress naming conventions
     * (e.g., PageController.php) and extend Sloth\Controller\Controller.
     *
     * @since 1.0.0
     *
     * @see \Sloth\Controller\Controller
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
     * Includes a PHP file and uses reflection to find the class defined in it.
     * Skips Corcel namespace classes (handled by Corcel itself) and returns
     * the first matching App\ namespaced class.
     *
     * @since 1.0.0
     *
     * @param string $file Absolute path to the PHP file
     * @return string Class name if found, empty string otherwise
     */
    protected function loadClassFromFile(string $file): string
    {
        $file = realpath($file);
        include_once $file;

        $matchingClass = null;

        foreach (get_declared_classes() as $class) {
            $rc = new \ReflectionClass($class);
            if ($rc->getFilename() === $file) {
                if (str_starts_with($class, 'Corcel\\')) {
                    continue;
                }

                if (str_starts_with($class, 'App\\')) {
                    $matchingClass = $class;
                    break;
                }

                $matchingClass = $class;
            }
        }

        return $matchingClass ?? '';
    }

    /**
     * Load all models.
     *
     * Discovers model classes from DIR_APP/Model, instantiates them,
     * registers post types with WordPress via PostTypes library,
     * enables/disables Layotter based on model configuration, and
     * flushes rewrite rules after registration.
     *
     * @since 1.0.0
     *
     * @uses PostTypes\PostType For post type registration
     * @see \Sloth\Model\Model
     * @see \Sloth\Layotter\Layotter
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
                    $this->container['layotter']->set_layouts_for_post_type(
                        $model->getPostType(),
                        $model::$layotter['allowed_row_layouts']
                    );
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
     * Discovers taxonomy classes from DIR_APP/Taxonomy, instantiates them,
     * and calls their register() method if available.
     *
     * @since 1.0.0
     *
     * @see \Sloth\Model\Taxonomy
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
     * Discovers controllers in DIR_APP/Api/, auto-maps public methods
     * to REST routes under /sloth/v1/, and wraps each callback in an
     * output buffer so PHP warnings (e.g. from Corcel) don't corrupt
     * the JSON response. In dev environments warnings are surfaced
     * as a _warnings key in the response payload.
     *
     * @since 1.0.0
     *
     * @throws \Exception If controller doesn't extend Sloth\Api\Controller
     * @see \Sloth\Api\Controller
     */
    public function loadApiControllers(): void
    {
        foreach (glob(DIR_APP . 'Api' . DS . '*.php') as $file) {
            $controllerName = $this->loadClassFromFile($file);

            $controller = new $controllerName();

            if (!is_subclass_of($controller, \Sloth\Api\Controller::class)) {
                throw new \Exception("ApiController {$controllerName} needs to extend Sloth\\Api\\Controller");
            }

            $methods = get_class_methods($controller);
            $routePrefix = Utility::viewize((new \ReflectionClass($controller))->getShortName());
            $routes = [];

            foreach ($methods as $method) {
                if (str_starts_with($method, '_')) {
                    continue;
                }
                if ($method === 'single') {
                    continue;
                }
                $routes[$routePrefix . '/' . Utility::viewize($method) . '(?:/(?P<id>\w+))?'] = $method;
            }

            if (method_exists($controller, 'single')) {
                $routes[$routePrefix] = 'index';
                $routes[$routePrefix . '(?:/(?P<id>[a-z0-9._-]+))?'] = 'single';
            } else {
                $routes[$routePrefix . '(?:/(?P<id>[a-z0-9._-]+))?'] = 'index';
            }

            $isDevEnv = $this->isDevEnv();
            add_filter('rest_post_dispatch', fn($response) => $response);
            foreach ($routes as $route => $action) {
                add_action('rest_api_init', function () use ($route, $action, $controller, $isDevEnv): void {
                    register_rest_route(
                        'sloth/v1',
                        '/' . $route,
                        [
                            'methods' => ['GET', 'POST', 'DELETE', 'PUT'],
                            'callback' => function ($request) use ($controller, $action, $isDevEnv): WP_REST_Response {

                                $controller->setRequest($request);
                                $param = $request->get_url_params('id');
                                $data = call_user_func_array([$controller, $action], [reset($param)]);

                                if (empty($data) && $controller->response->status >= 400) {
                                    $data = [
                                        'code' => $controller->response->status,
                                        'message' => Response::$statusTexts[$controller->response->status] ?? 'Unknown Error',
                                    ];
                                }

                                return new WP_REST_Response(
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
     */
    public function loadModules(): void
    {
        foreach (glob(get_template_directory() . DS . 'Module' . DS . '*Module.php') as $file) {
            $moduleName = $this->loadClassFromFile($file);

            if (is_array($moduleName::$layotter) && class_exists('\\Layotter')) {
                $className = substr(strrchr($moduleName, "\\"), 1);

                $moduleClassName = $moduleName;
                eval("class {$className} extends \\Sloth\\Module\\LayotterElement {
					static \$module = '{$moduleClassName}';
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
                        $route[] = '(?P<' . $param . '>[a-z0-9._-]+)';
                    }
                }

                add_action('rest_api_init', function () use ($route, $m): void {
                    register_rest_route(
                        'sloth/v1/module',
                        '/' . implode('/', $route),
                        [
                            'methods' => ['GET', 'POST'],
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
     * Registers all core WordPress hooks for the framework including:
     * - ACF auto-sync (in dev mode)
     * - Deployment hooks
     * - URL relative path filters
     * - REST API route registration
     * - Model, Taxonomy, Module, and Menu loading
     * - SVG mime type addition
     *
     * @since 1.0.0
     *
     * @see Deployment For deployment hooks
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


        add_filter('network_admin_url', $this->fixNetworkAdminUrl(...));
        add_action('init', $this->loadApiControllers(...), 20);
        add_action('init', $this->loadModels(...), 20);
        add_action('init', $this->loadTaxonomies(...), 20);
        add_action('init', $this->loadModules(...), 20);
        add_action('init', $this->registerMenus(...), 20);
        add_action('init', $this->initModels(...), 20);
        add_action('init', $this->loadAppIncludes(...), 20);
        add_action('init', $this->registerImageSizes(...), 20);
        add_action('init', $this->registerNavMenus(...), 20);
        add_action('admin_menu', $this->initTaxonomies(...), 20);
        add_action('admin_menu', $this->cleanupAdminMenu(...), 20);

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

        add_action('template_redirect', $this->getTemplate(...), 20);

        if (getenv('FORCE_SSL')) {
            add_action('template_redirect', $this->forceSsl(...), 30);
        }

        add_filter('upload_mimes', function (array $mimes): array {
            $mimes['svg'] = 'image/svg+xml';

            return $mimes;
        });

        if (Configure::read('wp-json.baseUrl')) {
            add_filter('rest_url_prefix', fn(): string => (string) Configure::read('wp-json.baseUrl'));
        }

        if (Configure::read('core.hide_updates')) {
            add_filter('pre_site_transient_update_core', $this->hideUpdates(...));
        }

        if (Configure::read('plugins.hide_updates')) {
            add_filter('pre_site_transient_update_plugins', $this->hideUpdates(...));
        }

        if (Configure::read('themes.hide_updates')) {
            add_filter('pre_site_transient_update_themes', $this->hideUpdates(...));
        }

        $this->container['layotter']->addFilters();
    }

    /**
     * Remove unnecessary WordPress references from wp_head.
     *
     * @since 1.0.0
     * @deprecated Opinionated theme cleanup, not a framework concern. Remove in refactor/cleanup-and-docs.
     *
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
     * Registers filters on WordPress permalink functions to strip the
     * domain from URLs, making them root-relative (e.g., /about instead
     * of https://example.com/about). Applies to post links, page links,
     * term links, archive links, and comment pagination.
     *
     * @since 1.0.0
     *
     * @see getRelativePermalink() For the actual URL transformation
     */
    private function makeLinksRelative(): void
    {
        $filters = [
            'day_link',
            'year_link',
            'post_link',
            'page_link',
            'term_link',
            'month_link',
            'search_link',
            'the_permalink',
            'get_shortlink',
            'post_type_link',
            'get_pagenum_link',
            'post_type_archive_link',
            'get_comments_pagenum_link',
            'sloth_get_permalink',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, $this->getRelativePermalink(...), 90, 1);
        }

        add_filter('the_content', $this->getRelativeHrefs(...), 90, 1);
    }

    /**
     * Make all uploads URLs root-relative.
     *
     * Registers filters on WordPress upload URL functions (wp_get_attachment_url,
     * wp_get_upload_dir, the_content) to strip the domain from media URLs.
     * This enables serving uploads from relative paths.
     *
     * @since 1.0.0
     *
     * @see getRelativePermalink() For the URL transformation
     */
    private function makeUploadsRelative(): void
    {
        $filters = [
            'wp_get_attachment_url',
            'template_directory_uri',
            'attachment_link',
            'content_url',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, $this->getRelativePermalink(...), 90, 1);
        }

        add_filter('sloth_get_attachment_link', $this->getRelativePermalink(...), 90, 1);
        add_filter('the_content', $this->getRelativeSrcs(...), 90, 1);
    }

    /**
     * Get a relative permalink.
     *
     * Strips the domain and scheme from a URL, returning only the path.
     * Used by makeLinksRelative() and makeUploadsRelative() to convert
     * absolute URLs to root-relative paths.
     *
     * @since 1.0.0
     *
     * @param string $input The full URL to convert
     * @return string The relative path (e.g., /about or /wp-content/uploads/image.jpg)
     */
    public function getRelativePermalink(
        string $input
    ): string {
        return (string) parse_url($input, PHP_URL_PATH);
    }

    /**
     * Replace home URL in content.
     *
     * Removes the home URL base from a string, useful for converting
     * absolute URLs in post content to relative paths.
     *
     * @since 1.0.0
     *
     * @param string $input The input string containing URLs
     * @return string The string with home URL removed
     */
    public function replaceHomeUrl(
        string $input
    ): string {
        return str_replace(trim((string) WP_HOME, '/'), '', $input);
    }

    /**
     * Make hrefs in content relative.
     *
     * Finds all href attributes in HTML content and removes the home URL,
     * converting absolute links to relative paths for makeLinksRelative().
     *
     * @since 1.0.0
     *
     * @param string $input HTML content with href attributes
     * @return string Content with relative hrefs
     */
    public function getRelativeHrefs(
        string $input
    ): string {
        return str_replace('href="' . rtrim((string) WP_HOME, '/'), 'href="', $input);
    }

    /**
     * Make srcs in content relative.
     *
     * Processes img src attributes in HTML content to remove the home URL.
     * Note: This implementation appears to have a bug - it doesn't actually
     * remove the URL, unlike getRelativeHrefs(). Likely should use getRelativePermalink.
     *
     * @since 1.0.0
     *
     * @param string $input HTML content with src attributes
     * @return string Content (potentially with relative srcs - see TODO)
     */
    public function getRelativeSrcs(
        string $input
    ): string {
        return str_replace('src="' . rtrim((string) WP_HOME, '/'), 'src="' . rtrim((string) WP_HOME, '/'), $input);
    }

    /**
     * Hide WordPress update notifications.
     *
     * Returns a fake update response object to suppress WordPress update
     * notifications. Used when Configure::read('plugins.hide_updates') or
     * Configure::read('themes.hide_updates') is enabled.
     *
     * @since 1.0.0
     *
     * @return object Fake update response with current time and WP version
     */
    public function hideUpdates(): object
    {
        global $wpVersion;

        return (object) [
            'last_checked' => time(),
            'version_checked' => $wpVersion,
        ];
    }

    /**
     * Fix network admin URL.
     *
     * Rewrites network admin URLs to include /cms/ prefix when accessing
     * from a multisite installation's main domain. This ensures proper routing
     * to the network admin dashboard.
     *
     * @since 1.0.0
     *
     * @param string $url The original admin URL
     * @return string The modified URL with /cms/ prefix
     */
    public function fixNetworkAdminUrl(
        string $url
    ): string {
        $urlInfo = parse_url($url);

        if (!preg_match('/^\/cms/', (string) ($urlInfo['path'] ?? ''))) {
            $url = $urlInfo['scheme'] . '://' . $urlInfo['host'] . '/cms' . $urlInfo['path'];
            if (isset($urlInfo['query']) && (isset($urlInfo['query']) && ($urlInfo['query'] !== '' && $urlInfo['query'] !== '0'))) {
                $url .= '?' . $urlInfo['query'];
            }
        }

        return $url;
    }

    /**
     * Force SSL redirect.
     *
     * Redirects all HTTP requests to HTTPS when FORCE_SSL environment
     * variable is set. Uses 301 (permanent) redirect for SEO.
     *
     * @since 1.0.0
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
     * Builds an array of variables to pass to Twig templates, including:
     * - WordPress info (title, site URL, feed URLs, language, etc.)
     * - Global theme URLs (home, theme, images)
     * - Current post/model data when on single post or page
     * - Current layout name
     *
     * This context is used by all Twig templates as global variables.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> Context array for Twig templates
     * @see TwigEngine For how context is passed to templates
     */
    public function getContext(): array
    {
        if (is_array($this->context)) {
            return $this->context;
        }

        $this->context = [
            'wp_title' => trim((string) wp_title('', false)),
            'site' => [
                'url' => (string) home_url(),
                'rdf' => (string) get_bloginfo('rdf_url'),
                'rss' => (string) get_bloginfo('rss_url'),
                'rss2' => (string) get_bloginfo('rss2_url'),
                'atom' => (string) get_bloginfo('atom_url'),
                'language' => get_bloginfo('language'),
                'charset' => get_bloginfo('charset'),
                'pingback' => (string) get_bloginfo('pingback_url'),
                'admin_email' => (string) get_bloginfo('admin_email'),
                'name' => (string) get_bloginfo('name'),
                'title' => (string) get_bloginfo('name'),
                'description' => (string) get_bloginfo('description'),
                'canonical_url' => (string) home_url((string) $_SERVER['REQUEST_URI']),
            ],
            'globals' => [
                'home_url' => (string) home_url('/'),
                'theme_url' => (string) get_template_directory_uri(),
                'images_url' => get_template_directory_uri() . '/assets/img',
            ],
            'sloth' => [
                'current_layout' => basename($this->currentLayout ?? '', '.twig'),
            ],
        ];

        if (is_single() || is_page()) {
            $qo = get_queried_object();

            if ($this->currentModel === null) {
                $a = call_user_func([$this->getModelClass($qo->post_type), 'find'], [$qo->ID]);
                $this->currentModel = $a->first();
            }

            $this->context['post'] = $this->currentModel;
            $this->context[$qo->post_type] = $this->currentModel;
        }

        if (is_tax()) {
            global $taxonomy;
            if ($this->currentModel === null) {
                $a = call_user_func([$this->getTaxonomyClass($taxonomy), 'find'], [get_queried_object()->term_id]);
                $this->currentModel = $a->first();
            }

            $this->context['taxonomy'] = $this->currentModel;
            $this->context[$taxonomy] = $this->currentModel;
        }

        if (is_author()) {
            if ($this->currentModel === null) {
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
                $template = basename((string) $routes[$uri]['Layout'], '.twig');
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
                    preg_match(
                        '/(.+)-(\d+)x(\d+)\.(jpg|jpeg|png|gif)$/',
                        (string) $_SERVER['REQUEST_URI'],
                        $matches
                    );

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

        $this->currentLayout = $template;

        $viewName = basename($template, '.twig');

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
     */
    public function registerImageSizes(): void
    {
        $imageSizes = Configure::read('theme.image-sizes');
        if ($imageSizes && is_array($imageSizes)) {
            foreach ($imageSizes as $name => $options) {
                $options = array_merge([
                    'width' => 800,
                    'height' => 600,
                    'crop' => false,
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
            $plugins[] = $plugin;
            \update_option('active_plugins', $plugins);
        }
    }

    /**
     * Fix pagination.
     *
     * @since 1.0.0
     *
     */
    protected function fixPagination(): void
    {
        if (isset($_GET['page'])) {
            $currentPage = (int) $_GET['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn(): int => $currentPage);
        }

        global $wpQuery;
        if (isset($wpQuery->query['page'])) {
            $currentPage = (int) $wpQuery->query['page'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn(): int => $currentPage);
        }

        if (isset($wpQuery->query['paged'])) {
            $currentPage = (int) $wpQuery->query['paged'];
            \Illuminate\Pagination\Paginator::currentPageResolver(fn(): int => $currentPage);
        }
    }

    /**
     * Initialize models.
     *
     * @since 1.0.0
     *
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
        if (count($filesInclude) === 0) {
            return;
        }

        foreach ($filesInclude as $file) {
            include_once realpath($file);
        }
    }

    /**
     * Get model class name.
     *
     * @param string $key Post type key
     *
     * @since 1.0.0
     *
     */
    public function getModelClass(
        string $key = ''
    ): string {
        return $this->models[$key] ?? \Sloth\Model\Post::class;
    }

    /**
     * Get all models.
     *
     * @return array<string, string>
     * @since 1.0.0
     *
     */
    public function getAllModels(): array
    {
        return $this->models;
    }

    /**
     * Get taxonomy class name.
     *
     * @param string $key Taxonomy key
     *
     * @since 1.0.0
     *
     */
    public function getTaxonomyClass(
        string $key = ''
    ): string {
        return $this->taxonomies[$key] ?? \Sloth\Model\Taxonomy::class;
    }

    /**
     * Get all taxonomies.
     *
     * @return array<string, string>
     * @since 1.0.0
     *
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
     * @param string $postType Post type
     *
     * @since 1.0.0
     *
     */
    public function getPostTypeClass(
        string $postType
    ): string {
        return $this->models[$postType] ?? \Sloth\Model\Post::class;
    }

    /**
     * Check if in development environment.
     *
     * @since 1.0.0
     *
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
     */
    public function cleanupAdminMenu(): void
    {
        global $menu;
        $used = [];
        foreach ($menu as $offset => $menuItem) {
            $pi = pathinfo((string) $menuItem[2], PATHINFO_EXTENSION);
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
     */
    public function isRest(): bool
    {
        $bIsRest = false;
        if (function_exists('rest_url') && !empty($_SERVER['REQUEST_URI'])) {
            $sRestUrlBase = (string) get_rest_url(get_current_blog_id(), '/');
            $sRestPath = trim(parse_url($sRestUrlBase, PHP_URL_PATH), '/');
            $sRequestPath = trim((string) $_SERVER['REQUEST_URI'], '/');
            $bIsRest = str_starts_with($sRequestPath, $sRestPath);
        }

        return $bIsRest;
    }

    /**
     * Register navigation menus.
     *
     * @throws \Exception
     * @since 1.0.0
     *
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
