<?php

declare(strict_types=1);
namespace Sloth\Options;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Options accessor.
 *
 * Binds Options as a singleton so WordPress' object cache
 * is leveraged across the request without extra caching overhead.
 *
 * @since 1.0.0
 */
class OptionsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(Options::class, fn (): Options => new Options());
        $this->app->alias(Options::class, 'options');
    }
}
