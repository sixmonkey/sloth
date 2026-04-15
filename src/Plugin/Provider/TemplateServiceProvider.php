<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Core\ServiceProvider;
use Sloth\Facades\Configure;
use Sloth\Facades\View;
use Sloth\Model\User;

/**
 * Service provider for template rendering and context management.
 *
 * Handles:
 * - Template resolution via Brain Hierarchy
 * - Twig context building for templates
 * - Current layout tracking
 * - Pagination fix for custom queries
 * - SSL force redirect
 * - REST URL prefix customization
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class TemplateServiceProvider extends ServiceProvider
{
    /**
     * Current theme path.
     *
     * @var string|null
     */
    protected ?string $currentThemePath = null;

    /**
     * Current layout.
     *
     * @var string|null
     */
    protected ?string $currentLayout = null;

    /**
     * Template context.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $context = null;

    /**
     * Current model instance.
     *
     * @var mixed
     */
    protected mixed $currentModel = null;

    /**
     * Set the current theme path.
     *
     * @since 1.0.0
     *
     * @param string $path The absolute path to the current theme
     */
    public function setThemePath(string $path): void
    {
        $this->currentThemePath = $path;
    }

    /**
     * Register template-related hooks.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        $hooks = [
            ['callback' => fn() => $this->getTemplate(), 'priority' => 20],
        ];

        if (getenv('FORCE_SSL')) {
            $hooks[] = ['callback' => fn() => $this->forceSsl(), 'priority' => 30];
        }

        return [
            'template_redirect' => $hooks,
        ];
    }

    /**
     * Register filters.
     *
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        $filters = [];

        if (Configure::read('wp-json.baseUrl')) {
            $filters['rest_url_prefix'] = fn() => (string) Configure::read('wp-json.baseUrl');
        }

        return $filters;
    }

    /**
     * Fix pagination for custom queries.
     *
     * @since 1.0.0
     */
    public function fixPagination(): void
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
     * Force HTTPS redirect.
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
     * Get the template context for Twig.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> Context array for Twig templates
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

        $this->populatePostContext();
        $this->populateTaxonomyContext();
        $this->populateAuthorContext();

        $this->app['sloth.context'] = $this->context;

        return $this->context;
    }

    /**
     * Populate post/page context.
     *
     * @since 1.0.0
     */
    protected function populatePostContext(): void
    {
        if (!is_single() && !is_page()) {
            return;
        }

        $qo = get_queried_object();

        if ($this->currentModel === null) {
            $models = $this->app['sloth.models'] ?? [];
            $modelClass = $models[$qo->post_type] ?? \Sloth\Model\Post::class;
            $a = call_user_func([$modelClass, 'find'], [$qo->ID]);
            $this->currentModel = $a->first();
        }

        $this->context['post'] = $this->currentModel;
        $this->context[$qo->post_type] = $this->currentModel;
    }

    /**
     * Populate taxonomy archive context.
     *
     * @since 1.0.0
     */
    protected function populateTaxonomyContext(): void
    {
        if (!is_tax()) {
            return;
        }

        global $taxonomy;
        if ($this->currentModel === null) {
            $taxonomies = $this->app['sloth.taxonomies'] ?? [];
            $taxonomyClass = $taxonomies[$taxonomy] ?? \Sloth\Model\Taxonomy::class;
            $a = call_user_func([$taxonomyClass, 'find'], [get_queried_object()->term_id]);
            $this->currentModel = $a->first();
        }

        $this->context['taxonomy'] = $this->currentModel;
        $this->context[$taxonomy] = $this->currentModel;
    }

    /**
     * Populate author archive context.
     *
     * @since 1.0.0
     */
    protected function populateAuthorContext(): void
    {
        if (!is_author()) {
            return;
        }

        if ($this->currentModel === null) {
            $this->currentModel = User::find(\get_queried_object()->id);
        }

        $this->context['user'] = $this->currentModel;
        $this->context['author'] = $this->currentModel;
    }

    /**
     * Get and render the template.
     *
     * @since 1.0.0
     */
    public function getTemplate(): void
    {
        $this->fixPagination();

        if (!is_dir($this->currentThemePath . DS . 'View' . DS . 'Layout')) {
            return;
        }

        global $post;
        $post = is_object($post) ? $post : new \StdClass();

        $template = $this->resolveTemplate();

        if ($template === '') {
            return;
        }

        if (post_password_required()) {
            $template = 'password-form';
        }

        $this->currentLayout = $template;

        $this->app['sloth.current_layout'] = basename($this->currentLayout, '.twig');

        $viewName = basename($template, '.twig');
        $view = View::make('Layout.' . $viewName);

        echo $view->with($this->getContext())->render();
        die();
    }

    /**
     * Resolve the template using Brain Hierarchy.
     *
     * @since 1.0.0
     *
     * @return string The resolved template path or empty string if none found
     */
    protected function resolveTemplate(): string
    {
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

                return $template;
            }
        }

        $layoutPaths = [];
        foreach ($this->app['view.finder']->getPaths() as $path) {
            $layoutPaths[] = $path . DS . 'Layout';
        }

        $finder = new ByFolders($layoutPaths, 'twig');
        $queryTemplate = new QueryTemplate($finder);

        return $queryTemplate->findTemplate(null, false);
    }

    /**
     * Get the current layout name.
     *
     * @since 1.0.0
     *
     * @return string|null The current layout name without extension
     */
    public function getCurrentLayout(): ?string
    {
        return basename($this->currentLayout ?? '', '.twig');
    }
}
