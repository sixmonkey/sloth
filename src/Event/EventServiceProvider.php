<?php

declare(strict_types=1);

namespace Sloth\Event;

use Illuminate\Events\Dispatcher;
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
    #[\Override]
    public function register(): void
    {
        $this->app->singleton('events', fn($app) => new Dispatcher($app));
    }
}