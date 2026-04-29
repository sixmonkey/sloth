<?php

declare(strict_types=1);

namespace Sloth\ACF;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for Advanced Custom Fields integration.
 *
 * Handles:
 * - ACF image field formatting (converting to Image objects)
 * - Auto-sync of ACF JSON field groups in local environments
 *
 * @since 1.0.0
 * @see \Sloth\ACF\ACFHelper
 */
class AcfServiceProvider extends ServiceProvider
{
    /**
     * Register the ACF service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton('acf.helper', fn(): ACFHelper => new ACFHelper());
    }

    /**
     * Get the filters for ACF
     *
     * @return array[]
     */
    public function getFilters(): array
    {
        return [
            'admin_init' => fn() => app('acf.helper')->autoSyncAcfFields(),
            /*'acf/format_value/type=image' => [
                'callback' => fn(...$args) => app('acf.helper')->loadImage(...$args),
                'priority' => PHP_INT_MAX
            ],*/
        ];
    }
}
