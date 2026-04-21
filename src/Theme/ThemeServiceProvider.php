<?php

declare(strict_types=1);

namespace Sloth\Theme;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for theme bootstrapping.
 *
 * Handles all theme-specific setup that must happen at the right point
 * in the boot sequence:
 *
 * ## Registration (register())
 *
 * Loads configuration files so that config values are available when
 * subsequent providers register their services.
 *
 * Order matters:
 * 1. `app/config/app.config.php` — procedural config (Configure::write, define)
 * 2. `app/config/*.php` — Laravel-style config files (return [...])
 * 3. `theme/config.php` — theme-specific config
 *
 * This must run before ViewServiceProvider so that theme.twig.filters
 * and other config values are available during Twig setup.
 *
 * ## Boot (boot())
 *
 * Sets up theme view paths after ViewServiceProvider has registered
 * view.finder and twig.loader in the container.
 *
 * @since 1.0.0
 * @see \Sloth\View\ViewServiceProvider
 */
class ThemeServiceProvider extends ServiceProvider
{
    /**
     * The current theme path.
     *
     * @since 1.0.0
     */
    private string $themePath;

    /**
     * Register theme configuration.
     *
     * Loads theme-specific config before other providers register.
     * Must run after ConfigureServiceProvider — which handles app/config/*.php.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->themePath = realpath(get_template_directory());

        // Make theme path available in the container
        $this->app->instance('theme.path', $this->themePath);

        // Load develop.config.php if present — local overrides
        @include $this->themePath . '/develop.config.php';

        // Load theme config.php — may register theme.twig.filters etc.
        $themeConfig = $this->themePath . '/config.php';
        if (file_exists($themeConfig)) {
            include_once $themeConfig;
        }

    }

    /**
     * Set up theme view paths and Twig loader.
     *
     * Must run after ViewServiceProvider — requires view.finder
     * and twig.loader to be bound in the container.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if (is_dir($this->themePath . '/View')) {
            $this->app['view.finder']->addLocation($this->themePath . '/View');
        }

        $this->app['view.finder']->addLocation($this->app->path('_view', 'framework'));
        $this->app['twig.loader']->setPaths($this->app['view.finder']->getPaths());
    }

}
