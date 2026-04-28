<?php

declare(strict_types=1);

namespace Sloth\Template;

use Brain\Hierarchy\Finder\ByFolders;
use Brain\Hierarchy\QueryTemplate;
use Sloth\Core\ServiceProvider;
use Sloth\Facades\View;

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
     * Set the current theme path.
     *
     * @since 1.0.0
     *
     */
    public function boot(): void
    {
        $this->currentThemePath = get_template_directory();
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

        if (config('wp-json.baseUrl')) {
            $filters['rest_url_prefix'] = fn() => (string) config('wp-json.baseUrl');
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
        if (env('FORCE_SSL', false) && !\is_ssl()) {
            \wp_redirect('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 301);
            exit();
        }
    }


    /**
     * Get and render the template.
     *
     * @since 1.0.0
     */
    public function getTemplate(): void
    {
        $this->fixPagination();

        if (!app('files')->isDirectory($this->currentThemePath . '/' . 'View' . '/' . 'Layout')) {
            return;
        }

        global $post;
        $post = is_object($post) ? $post : new \StdClass();

        $template = $this->resolveTemplate();

        if ($template === '') {
            return;
        }

        if (\post_password_required()) {
            $template = 'password-form';
        }

        $this->currentLayout = $template;

        $this->app['sloth.current_layout'] = basename($this->currentLayout, '.twig');

        $viewName = basename($template, '.twig');
        $view = View::make('Layout.' . $viewName);

        echo $view->with(app('context')->getContext())->render();
        die();
    }

    /**
     * Resolve the template using Brain Hierarchy.
     *
     * @return string The resolved template path or empty string if none found
     * @since 1.0.0
     *
     */
    protected function resolveTemplate(): string
    {
        if (config('theme.routes') && is_array(config('theme.routes'))) {
            $uri = (string) $_SERVER['REQUEST_URI'];

            if (false !== $pos = strpos($uri, '?')) {
                $uri = substr($uri, 0, $pos);
            }

            $uri = rtrim(rawurldecode($uri), '/');

            $routes = config('theme.routes');

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
            $layoutPaths[] = $path . '/' . 'Layout';
        }

        $finder = new ByFolders($layoutPaths, 'twig');
        $queryTemplate = new QueryTemplate($finder);

        return $queryTemplate->findTemplate(null, false);
    }

    /**
     * Get the current layout name.
     *
     * @return string|null The current layout name without extension
     * @since 1.0.0
     *
     */
    public function getCurrentLayout(): ?string
    {
        return basename($this->currentLayout ?? '', '.twig');
    }
}
