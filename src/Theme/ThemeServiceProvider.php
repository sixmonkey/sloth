<?php

declare(strict_types=1);
namespace Sloth\Theme;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for theme bootstrapping.
 *
 * Handles all theme-specific setup that must happen at the right point
 * in the boot sequence:
 *
 * ## Registration (register())
 *
 * Merges default theme config and loads any theme-specific config files
 * so that config values are available when subsequent providers register
 * their services.
 *
 * ## Boot (boot())
 *
 * Publishes config files, registers theme supports and sets up view paths
 * after ViewServiceProvider has registered view.finder and twig.loader
 * in the container.
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
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/theme.php', 'theme');

        $this->themePath = realpath(get_template_directory());

        // Make theme path available in the container
        $this->app->instance('theme.path', $this->themePath);
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
        $this->publishes([
            __DIR__ . '/config/theme.php' => app()->path('config', 'theme') . '/theme.php',
        ], 'config');

        if (is_dir($this->themePath . '/View')) {
            $this->app['view.finder']->addLocation($this->themePath . '/View');
        }

        $this->app['view.finder']->addLocation($this->app->path('_view', 'framework'));
        $this->app['twig.loader']->setPaths($this->app['view.finder']->getPaths());

    }

    /*
     * Add theme supports from config
     *
     * Can be configured either as a simple value
     * or as key => value pair
     *
     * Example:
     *   'supports' => [
     *       'menus',
     *       'html5' =>  [
     *           'search-form',
     *           'comment-form',
     *           'comment-list',
     *           'gallery',
     *           'caption',
     *       ]
     *   ],
     *
     * @since 1.0.2
     */
    protected function addThemeSupports(): void
    {
        if (!function_exists('add_theme_support')) {
            return;
        }

        collect(config('theme.supports', []))
            ->each(function ($value, $key): void {
                if (is_int($key)) {
                    add_theme_support($value);
                } else {
                    add_theme_support($key, $value);
                }
            })
        ;
    }

    /**
     * Register WordPress action hooks.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        return [
            'after_setup_theme' => $this->addThemeSupports(...),
        ];
    }
}
