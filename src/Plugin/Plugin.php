<?php

declare(strict_types=1);

namespace Sloth\Plugin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Facades\Configure;
use Sloth\Singleton\Singleton;
use Sloth\Template\TemplateServiceProvider;

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

        $this->container = app();
        $this->setupTheme();
        $this->container->boot();
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
     * Get the template context.
     *
     * Delegates to TemplateServiceProvider's getContext() method.
     * Kept for backwards compatibility with themes that access this method
     * via $GLOBALS['sloth::plugin']->getContext().
     *
     * @return array<string, mixed>
     * @throws BindingResolutionException
     * @since 1.0.0
     *
     */
    public function getContext(): array
    {
        if (app('context') !== null) {
            return app('context')->getContext();
        }

        return [];
    }

    /**
     * Check if this is a development environment.
     *
     * @return bool True if in development mode
     * @throws BindingResolutionException
     * @deprecated Use app()->isLocal() instead
     *
     * @since 1.0.0
     *
     */
    public function isDevEnv(): bool
    {
        return app()->isLocal();
    }
}
