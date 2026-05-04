<?php

declare(strict_types=1);

namespace Sloth\Event;

use Illuminate\Contracts\Events\Dispatcher;
use Sloth\Core\ServiceProvider;

/**
 * WordPress Event Bridge Service Provider.
 *
 * Bridges WordPress hooks (actions and filters) to the Laravel event system.
 *
 * Each WordPress hook registered in the config is wrapped with add_action()
 * or add_filter() to dispatch a Laravel event with the name 'wp:{hook}'.
 * Listeners receive a WpHookFired instance containing the hook name,
 * arguments, type, and (for filters) the modifiable result.
 *
 * Performance: The bridge only dispatches hooks that have active Laravel
 * listeners. If no listener is registered for 'wp:some_hook', the WordPress
 * callback returns immediately without creating event objects or invoking
 * the dispatcher. This makes it safe to register many hooks in the config
 * without performance penalty.
 *
 * Usage:
 *
 *   // Listen to a WordPress action
 *   Event::listen('wp:wp_loaded', function (WpHookFired $event) {
 *       dump('WordPress is fully loaded');
 *   });
 *
 *   // Modify a WordPress filter result
 *   Event::listen('wp:the_content', function (WpHookFired $event) {
 *       $event->result = strtoupper($event->result);
 *   });
 *
 *   // Dynamically register an additional hook
 *   $bridge = app(WordPressEventBridge::class);
 *   $bridge->addHook('my_custom_hook', 'action');
 *   Event::listen('wp:my_custom_hook', fn(WpHookFired $e) => dump($e->args));
 *
 * @since 1.0.0
 */
class WordPressEventBridge extends ServiceProvider
{
    /**
     * WordPress hooks that have been registered by this bridge.
     *
     * Prevents duplicate registration of the same hook.
     *
     * @var array<string, array{type: string, callback: callable}>
     */
    protected array $registeredHooks = [];

    /**
     * Register the bridge services.
     *
     * Makes the bridge instance accessible via the container so that
     * other providers can dynamically add hooks at runtime.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(WordPressEventBridge::class, fn($app) => $this);
    }

    /**
     * Bootstrap the WordPress event bridge.
     *
     * Merges the events configuration and registers all configured
     * WordPress hooks as Laravel event dispatchers.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $configRepo = $this->app->bound('config') ? $this->app->make('config') : null;
        if ($configRepo === null) {
            // Fallback: load config directly
            $configRepo = new \Illuminate\Config\Repository([
                'events' => include __DIR__ . '/config/events.php',
            ]);
            $this->app->instance('config', $configRepo);
        } else {
            $configRepo->set('events', array_replace_recursive(
                $configRepo->get('events', []),
                include __DIR__ . '/config/events.php'
            ));
        }

        $hooks = $configRepo->get('events.bridge', []);

        foreach ($hooks as $hook => $type) {
            $this->registerHook((string) $hook, (string) $type);
        }
    }

    /**
     * Register a single WordPress hook as a Laravel event dispatcher.
     *
     * For actions: dispatches the event but ignores the return value.
     * For filters: dispatches the event and returns $event->result,
     *              allowing listeners to modify the filtered value.
     *
     * If the event dispatcher is not yet bound or no listeners are
     * registered for the hook, the callback returns immediately
     * to avoid unnecessary overhead.
     *
     * @param string $hook The WordPress hook name (e.g., 'wp_loaded').
     * @param string $type The hook type: 'action' or 'filter'.
     * @since 1.0.0
     */
    public function registerHook(string $hook, string $type): void
    {
        // Prevent duplicate registration
        if (isset($this->registeredHooks[$hook])) {
            return;
        }

        // Build the bridge callback
        $callback = $this->makeBridgeCallback($hook, $type);

        // Register with WordPress
        // Priority: PHP_INT_MAX — run AFTER all other hooks so that the
        // WordPress state is fully settled when the Laravel event fires.
        // This ensures listeners see the final state, not an intermediate one.
        if ($type === 'filter') {
            // Filters need to accept at least 1 arg (the value being filtered)
            // We use 99 as accepted_args to handle filters with many arguments
            \add_filter($hook, $callback, PHP_INT_MAX, 99);
        } else {
            \add_action($hook, $callback, PHP_INT_MAX, 99);
        }

        // Track registration
        $this->registeredHooks[$hook] = [
            'type'     => $type,
            'callback' => $callback,
        ];
    }

    /**
     * Dynamically register an additional WordPress hook.
     *
     * This method allows other providers (e.g., CollectorProviders) to
     * request additional hooks at runtime. The hook will be registered
     * immediately if it hasn't been registered yet.
     *
     * Note: If the WordPress hook has already fired, registering it
     * here will have no effect. Use did_action() to check if a hook
     * has already fired before adding it.
     *
     * @param string $hook The WordPress hook name.
     * @param string $type The hook type: 'action' (default) or 'filter'.
     * @since 1.0.0
     */
    public function addHook(string $hook, string $type = 'action'): void
    {
        $this->registerHook($hook, $type);
    }

    /**
     * Create the bridge callback for a WordPress hook.
     *
     * The returned closure checks if the event dispatcher is available
     * and if any Laravel listeners are registered for the 'wp:{hook}'
     * event. If so, it creates a WpHookFired instance and dispatches it.
     *
     * For filter hooks, the closure returns $event->result to allow
     * listeners to modify the filtered value.
     *
     * @param string $hook The WordPress hook name.
     * @param string $type The hook type: 'action' or 'filter'.
     * @return callable The WordPress hook callback.
     * @since 1.0.0
     */
    protected function makeBridgeCallback(string $hook, string $type): callable
    {
        return function (...$args) use ($hook, $type): mixed {
            // Skip if the event dispatcher is not yet available
            // This can happen if the bridge boots before EventServiceProvider
            if (! $this->app->bound('events')) {
                return $type === 'filter' ? ($args[0] ?? null) : null;
            }

            /** @var Dispatcher $dispatcher */
            $dispatcher = $this->app->make('events');

            // Only dispatch if there are active listeners
            // This avoids unnecessary object creation and dispatcher overhead
            $eventName = "wp:{$hook}";
            if (! $dispatcher->hasListeners($eventName)) {
                return $type === 'filter' ? ($args[0] ?? null) : null;
            }

            // Build the event
            // For filters: $args[0] is the value being filtered → set as $result
            $result = $type === 'filter' ? ($args[0] ?? null) : null;
            $event = new WpHookFired(
                hook: $hook,
                args: $args,
                type: $type,
                result: $result,
            );

            // Dispatch to Laravel event system
            $dispatcher->dispatch($eventName, $event);

            // For filters, return the (potentially modified) result
            // For actions, return value is ignored by WordPress
            return $event->result;
        };
    }

    /**
     * Get the list of registered WordPress hooks.
     *
     * @return array<string, array{type: string, callback: callable}>
     * @since 1.0.0
     */
    public function getRegisteredHooks(): array
    {
        return $this->registeredHooks;
    }

    /**
     * Check if a specific WordPress hook is registered by this bridge.
     *
     * @param string $hook The WordPress hook name.
     * @return bool True if the hook is registered.
     * @since 1.0.0
     */
    public function hasHook(string $hook): bool
    {
        return isset($this->registeredHooks[$hook]);
    }
}
