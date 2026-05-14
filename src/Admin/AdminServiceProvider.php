<?php

declare(strict_types=1);
namespace Sloth\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Admin Customizer component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register the Customizer and merge default config.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/admin.php', 'admin');

        $this->app->singleton(
            'customizer',
            fn ($container): Customizer => new Customizer($container),
        );
    }

    /**
     * Boot — publish config.
     *
     * @since 1.0.0
     */
    #[Override]
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/admin.php' => app()->path('config') . '/admin.php',
        ], 'config');
    }

    /**
     * Add the filters for the Customizer.
     *
     * @throws BindingResolutionException
     *
     * @return array[]
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        $filters = [];

        if (config('admin.footer', true)) {
            $filters['update_footer'] = ['callback' => fn () => app('customizer')->renderFooter(), 'priority' => PHP_INT_MAX];
        }

        if (config('admin.hide_updates.core', false)) {
            $filters['pre_site_transient_update_core'] = fn ($t) => app('customizer')->hideUpdates($t);
        }

        if (config('admin.hide_updates.plugins', false)) {
            $filters['pre_site_transient_update_plugins'] = fn ($t) => app('customizer')->hideUpdates($t);
        }

        if (config('admin.hide_updates.themes', false)) {
            $filters['pre_site_transient_update_themes'] = fn ($t) => app('customizer')->hideUpdates($t);
        }

        return $filters;
    }

    /**
     * Register hooks.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        $hooks = [];

        if (config('admin.cleanup_menu', true)) {
            $hooks['admin_menu'] = ['callback' => fn () => app('customizer')->cleanupAdminMenu(), 'priority' => 20];
        }

        return $hooks;
    }
}
