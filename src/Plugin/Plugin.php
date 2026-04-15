<?php

declare(strict_types=1);

namespace Sloth\Plugin;

use Sloth\ACF\ACFHelper;
use Sloth\Facades\Configure;
use Sloth\Facades\Deployment;
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
        $adminProvider = new AdminServiceProvider();
        $mediaProvider = new MediaServiceProvider();
        $menuProvider = new MenuServiceProvider();
        $taxonomyProvider = new TaxonomyServiceProvider();
        $modelProvider = new ModelServiceProvider();
        $apiProvider = new ApiServiceProvider();
        $moduleProvider = new ModuleServiceProvider();
        $templateProvider = new TemplateServiceProvider();

        $taxonomyProvider->setContainer($this->container);
        $modelProvider->setContainer($this->container);
        $templateProvider->setContainer($this->container);
        $templateProvider->setThemePath($this->current_theme_path);

        $this->providers = [
            $adminProvider,
            $mediaProvider,
            $menuProvider,
            $taxonomyProvider,
            $modelProvider,
            $apiProvider,
            $moduleProvider,
            $templateProvider,
        ];

        $adminProvider->register();
        $mediaProvider->register();
        $menuProvider->register();
        $taxonomyProvider->register();
        $taxonomyProvider->boot();
        $modelProvider->register();
        $apiProvider->register();
        $moduleProvider->register();
        $templateProvider->register();

        $this->templateProvider = $templateProvider;

        ACFHelper::getInstance();
        Deployment::getInstance()->boot();
        $this->container['layotter']->addFilters();
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
}
