<?php

declare(strict_types=1);

namespace Sloth\ACF;

use Sloth\Core\ServiceProvider;

/**
 * ACF Service Provider
 *
 * @since 1.0.0
 */
class AcfServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ACFHelper::class);
    }

    public function boot(): void
    {
        $this->app->call([$this->app->make(ACFHelper::class), 'addFilters']);
    }
}
