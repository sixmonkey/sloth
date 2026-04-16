<?php

declare(strict_types=1);

namespace Sloth\ACF;

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
class AcfServiceProvider
{
    /**
     * Register the ACF service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
    }

    /**
     * Boot the ACF service provider.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $acfHelper = new ACFHelper();
        $acfHelper->addFilters();
    }
}
