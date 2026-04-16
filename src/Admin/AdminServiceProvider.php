<?php

declare(strict_types=1);

namespace Sloth\Admin;

use Illuminate\Contracts\Container\BindingResolutionException;
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
     * Register the Customizer service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'customizer',
            fn($container): \Sloth\Admin\Customizer => Customizer::getInstance()
        );
    }

    /**
     * Add the filters for the Customizer.
     *
     * @return array[]
     * @throws BindingResolutionException
     */
    public function getFilters(): array
    {
        $filters = [
            'update_footer' => ['callback' => fn() => app('customizer')->renderFooter(), 'priority' => PHP_INT_MAX],
        ];

        if (config('core.hide_updates', true)) {
            $filters['pre_site_transient_update_core'] = fn($t) => app('customizer')->hideUpdates($t);
        }

        if (config('plugins.hide_updates', true)) {
            $filters['pre_site_transient_update_plugins'] = fn($t) => app('customizer')->hideUpdates($t);
        }

        if (config('themes.hide_updates', true)) {
            $filters['pre_site_transient_update_themes'] = fn($t) => app('customizer')->hideUpdates($t);
        }

        return $filters;
    }

    /**
     * Register hooks.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'admin_menu' => ['callback' => fn() => app('customizer')->cleanupAdminMenu(), 'priority' => 20],
        ];
    }
}
