<?php

declare(strict_types=1);
namespace Sloth\Event;

use Illuminate\Events\Dispatcher;
use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for Events/Dispatcher.
 *
 * Registers the events dispatcher for the application.
 *
 * @since 1.0.0
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * Register the events service.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton('events', fn ($app): Dispatcher => new Dispatcher($app));
    }

    /**
     * Boots the EventServiceProviders and publishes it's config
     *
     * @since 1.0.2
     */
    #[Override]
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/events.php' => app()->path('config', 'app') . '/events.php',
        ], 'config');
    }
}
