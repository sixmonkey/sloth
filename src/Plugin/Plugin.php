<?php

declare(strict_types=1);

namespace Sloth\Plugin;

use Sloth\ACF\AcfServiceProvider;
use Sloth\Facades\Configure;
use Sloth\Plugin\Provider\AdminServiceProvider;
use Sloth\Plugin\Provider\ApiServiceProvider;
use Sloth\Plugin\Provider\MediaServiceProvider;
use Sloth\Plugin\Provider\MenuServiceProvider;
use Sloth\Plugin\Provider\ModelServiceProvider;
use Sloth\Plugin\Provider\ModuleServiceProvider;
use Sloth\Plugin\Provider\TaxonomyServiceProvider;
use Sloth\Plugin\Provider\TemplateServiceProvider;
use Sloth\Singleton\Singleton;

/**
 * Theme bootstrapper for Sloth framework.
 *
 * This class serves as a thin bootstrapper that:
 * - Sets up theme paths and configuration
 * - Registers all service providers in the correct order
 * - Configures view paths for Twig
 *
 * All core functionality has been extracted into focused ServiceProviders:
 * - AdminServiceProvider: Admin UI, update notifications
 * - MediaServiceProvider: Image sizes, SVG, relative URLs
 * - MenuServiceProvider: Navigation menu registration
 * - TaxonomyServiceProvider: Taxonomy registration and metaboxes
 * - ModelServiceProvider: Post type registration and columns
 * - ApiServiceProvider: REST API controller routing
 * - ModuleServiceProvider: Module discovery and Layotter integration
 * - TemplateServiceProvider: Template rendering and context
 *
 * ## Provider Order
 *
 * Providers must be registered in a specific order due to dependencies:
 * 1. AdminServiceProvider - Admin hooks (can register early)
 * 2. MediaServiceProvider - Media/URL handling
 * 3. MenuServiceProvider - Navigation menus
 * 4. TaxonomyServiceProvider - Taxonomies (before models for association)
 * 5. ModelServiceProvider - Post types (depends on taxonomies)
 * 6. ApiServiceProvider - REST routes
 * 7. ModuleServiceProvider - Module loading
 * 8. TemplateServiceProvider - Template rendering (depends on models/taxonomies)
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Provider
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
     * Service providers.
     *
     * @var array<int, object>
     */
    private array $providers = [];

    /**
     * Template service provider reference.
     *
     * Stored to delegate getContext() calls.
     *
     * @since 1.0.0
     */
    private ?TemplateServiceProvider $templateProvider = null;

    /**
     * Plugin constructor.
     *
     * Initializes the theme by:
     * 1. Setting up the container and paths
     * 2. Loading theme config and routes
     * 3. Registering all service providers
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        if (!is_blog_installed()) {
            return;
        }

        $this->container = $GLOBALS['sloth']->container;
        $this->setupTheme();
        $this->registerProviders();
    }

    /**
     * Set up theme paths and configuration.
     *
     * Initializes:
     * - Current theme path
     * - Theme container path
     * - View locations (theme and framework)
     * - Theme config.php
     * - Routes file
     * - Default configuration
     *
     * @since 1.0.0
     */
    protected function setupTheme(): void
    {
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
     * Register all service providers.
     *
     * Registers providers in the correct order to ensure proper dependencies.
     * Each provider's register() method is called to set up WordPress hooks.
     *
     * @since 1.0.0
     */
    protected function registerProviders(): void
    {
        $providerClasses = [
            AdminServiceProvider::class,
            MediaServiceProvider::class,
            MenuServiceProvider::class,
            TaxonomyServiceProvider::class,
            ModelServiceProvider::class,
            AcfServiceProvider::class,
            ApiServiceProvider::class,
            ModuleServiceProvider::class,
            TemplateServiceProvider::class,
        ];

        foreach ($providerClasses as $providerClass) {
            $this->providers[] = $this->container->register($providerClass);
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof TemplateServiceProvider) {
                $provider->setThemePath($this->current_theme_path);
                break;
            }
        }

        $this->registerProviderHooks();

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        foreach ($this->providers as $provider) {
            if ($provider instanceof TemplateServiceProvider) {
                $this->templateProvider = $provider;
                break;
            }
        }

        $this->container['layotter']->addFilters();
    }

    /**
     * Normalize callbacks from getHooks/getFilters format.
     *
     * @since 1.0.0
     *
     * @param mixed $value
     *
     * @return array<int, array{fn: callable, priority: int}>
     */
    private function normalizeCallbacks(mixed $value): array
    {
        if (is_callable($value)) {
            return [['fn' => $value, 'priority' => 10]];
        }

        if (isset($value['callback'])) {
            return [['fn' => $value['callback'], 'priority' => $value['priority'] ?? 10]];
        }

        return array_map(function ($item) {
            if (is_callable($item)) {
                return ['fn' => $item, 'priority' => 10];
            }

            return ['fn' => $item['callback'], 'priority' => $item['priority'] ?? 10];
        }, $value);
    }

    /**
     * Register all WordPress hooks and filters from providers.
     *
     * @since 1.0.0
     */
    private function registerProviderHooks(): void
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getHooks() as $hook => $value) {
                foreach ($this->normalizeCallbacks($value) as $callback) {
                    add_action($hook, $callback['fn'], $callback['priority']);
                }
            }

            foreach ($provider->getFilters() as $filter => $value) {
                foreach ($this->normalizeCallbacks($value) as $callback) {
                    add_filter($filter, $callback['fn'], $callback['priority']);
                }
            }
        }
    }

    /**
     * Get the container instance.
     *
     * @since 1.0.0
     *
     * @return mixed
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Get all registered providers.
     *
     * @since 1.0.0
     *
     * @return array<int, object>
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get the template context.
     *
     * Delegates to TemplateServiceProvider's getContext() method.
     * Kept for backwards compatibility with themes that access this method
     * via $GLOBALS['sloth::plugin']->getContext().
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        if ($this->templateProvider !== null) {
            return $this->templateProvider->getContext();
        }

        return [];
    }

    /**
     * Check if this is a development environment.
     *
     * @deprecated Use app()->isLocal() instead
     *
     * @since 1.0.0
     *
     * @return bool True if in development mode
     */
    public function isDevEnv(): bool
    {
        return app()->isLocal();
    }
}
