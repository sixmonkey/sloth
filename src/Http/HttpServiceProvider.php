<?php

declare(strict_types=1);

namespace Sloth\Http;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for HTTP infrastructure.
 *
 * Binds Sloth's HTTP classes into the container so Facades
 * and direct resolution work correctly.
 *
 * @since 1.0.0
 */
class HttpServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->bind('response', fn() => new Response());
    }
}
