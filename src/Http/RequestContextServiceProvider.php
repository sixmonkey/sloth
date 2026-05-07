<?php

declare(strict_types=1);
namespace Sloth\Http;

use Inpsyde\WpContext;
use Override;
use Sloth\Core\ServiceProvider;

/**
 * Registers the RequestContext singleton in the container.
 *
 * RequestContext wraps inpsyde/wp-context for WordPress request
 * detection with early REST/AJAX detection.
 *
 * @since 1.0.0
 * @see RequestContext
 */
class RequestContextServiceProvider extends ServiceProvider
{
    /**
     * Register the RequestContext singleton.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(RequestContext::class, fn ($app): RequestContext => new RequestContext(
            wpContext: WpContext::determine(),
            restPrefix: env('WP_REST_PREFIX'),
        ));

        $this->app->alias(RequestContext::class, 'request-context');
    }
}
