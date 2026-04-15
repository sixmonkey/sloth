<?php

declare(strict_types=1);

namespace Sloth\ACF;

/**
 * ACF Service Provider
 *
 * @since 1.0.0
 */
class AcfServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        $acfHelper = new ACFHelper();
        $acfHelper->addFilters();
    }
}
