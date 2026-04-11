<?php

declare(strict_types=1);

namespace Sloth\Finder;

use Illuminate\Filesystem\Filesystem;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Finder component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class FinderServiceProvider extends ServiceProvider
{
    /**
     * Register the Finder service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->bind(
            'filesystem',
            fn(): Filesystem => new Filesystem()
        );
    }
}
