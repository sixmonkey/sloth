<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Event;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use PHPUnit\Framework\TestCase;
use Sloth\Event\WpHookFired;
use Sloth\Event\WordPressEventBridge;

/**
 * Unit tests for the WordPressEventBridge service provider.
 *
 * These tests verify that the bridge correctly registers WordPress hooks,
 * dispatches Laravel events, and handles filter bidirectionality.
 *
 * @since 1.0.0
 */
class WordPressEventBridgeTest extends TestCase
{
    /**
     * The application container instance.
     */
    protected Container $app;

    /**
     * The bridge instance under test.
     */
    protected WordPressEventBridge $bridge;

    /**
     * Set up the test environment with a fresh container and bridge.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a fresh container
        $this->app = new Container();

        // Create and bind the event dispatcher
        $this->app->singleton('events', fn(): \Illuminate\Events\Dispatcher => new Dispatcher($this->app));
        $this->app->alias('events', Dispatcher::class);

        // Create the bridge
        $this->bridge = new WordPressEventBridge($this->app);
        $this->bridge->register();
    }

    /**
     * Test that register() creates a singleton in the container.
     */
    public function test_register_creates_singleton(): void
    {
        $this->app = new Container();
        $bridge = new WordPressEventBridge($this->app);
        $bridge->register();

        $this->assertTrue($this->app->bound(WordPressEventBridge::class));
    }

    /**
     * Test that boot() loads config and registers hooks.
     *
     * The bridge should read from 'events.bridge' config and register
     * each hook with WordPress.
     */
    public function test_boot_registers_hooks_from_config(): void
    {
        // Set minimal config for this test
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'wp_loaded' => 'action',
                'the_content' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        $this->assertTrue($this->bridge->hasHook('wp_loaded'));
        $this->assertTrue($this->bridge->hasHook('the_content'));
    }

    /**
     * Test that duplicate hook registration is prevented.
     */
    public function test_register_hook_prevents_duplicates(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'wp_loaded' => 'action',
            ],
        ]));

        $this->bridge->boot();
        $this->bridge->registerHook('wp_loaded', 'action');

        // Should still only have one entry
        $registered = $this->bridge->getRegisteredHooks();
        $this->assertCount(1, $registered);
    }

    /**
     * Test that addHook() dynamically registers a new hook.
     */
    public function test_add_hook_dynamically_registers_hook(): void
    {
        $this->bridge->addHook('my_custom_hook', 'action');

        $this->assertTrue($this->bridge->hasHook('my_custom_hook'));
    }

    /**
     * Test that dispatch is skipped when no listeners are registered.
     *
     * The bridge should check hasListeners() before creating event objects,
     * avoiding unnecessary overhead.
     */
    public function test_no_dispatch_without_listeners(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'wp_loaded' => 'action',
            ],
        ]));

        $this->bridge->boot();

        // Get the registered callback and invoke it
        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['wp_loaded']['callback'];

        // No listeners registered — callback should return early
        $result = $callback();

        // Action hooks return null
        $this->assertNull($result);
    }

    /**
     * Test that action hooks dispatch events to listeners.
     */
    public function test_action_hooks_dispatch_to_listeners(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'init' => 'action',
            ],
        ]));

        $this->bridge->boot();

        // Track if listener was called
        $listenerCalled = false;
        $receivedEvent = null;

        $this->app->make('events')->listen('wp:init', function (WpHookFired $event) use (&$listenerCalled, &$receivedEvent): void {
            $listenerCalled = true;
            $receivedEvent = $event;
        });

        // Trigger the bridge callback
        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['init']['callback'];
        $callback('arg1', 'arg2');

        $this->assertTrue($listenerCalled);
        $this->assertInstanceOf(WpHookFired::class, $receivedEvent);
        $this->assertSame('init', $receivedEvent->hook);
        $this->assertSame('action', $receivedEvent->type);
        $this->assertSame(['arg1', 'arg2'], $receivedEvent->args);
    }

    /**
     * Test that filter hooks dispatch events and return modified result.
     */
    public function test_filter_hooks_return_modified_result(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'the_content' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        // Listener modifies the result
        $this->app->make('events')->listen('wp:the_content', function (WpHookFired $event): void {
            $event->result = '<p>Modified: ' . $event->result . '</p>';
        });

        // Trigger the bridge callback with original content
        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['the_content']['callback'];
        $result = $callback('<p>Original</p>');

        $this->assertSame('<p>Modified: <p>Original</p></p>', $result);
    }

    /**
     * Test that filter hooks return original value when no listeners modify it.
     */
    public function test_filter_hooks_return_original_result_if_unmodified(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'the_title' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        // Listener that does NOT modify the result
        $this->app->make('events')->listen('wp:the_title', function (WpHookFired $event): void {
            // Just observe, don't modify
            $this->assertSame('Hello World', $event->result);
        });

        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['the_title']['callback'];
        $result = $callback('Hello World');

        $this->assertSame('Hello World', $result);
    }

    /**
     * Test that filter hooks return original value when no listeners exist.
     */
    public function test_filter_hooks_return_original_value_without_listeners(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'the_content' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        // No listeners registered
        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['the_content']['callback'];
        $result = $callback('<p>Content</p>');

        $this->assertSame('<p>Content</p>', $result);
    }

    /**
     * Test that bridge gracefully handles missing event dispatcher.
     *
     * If the bridge callback fires before EventServiceProvider has bound
     * 'events', it should return the original filter value without error.
     */
    public function test_graceful_handling_when_dispatcher_not_bound(): void
    {
        new WordPressEventBridge($this->app);

        // Manually create a callback without booting (so no hooks registered)
        // Simulate a scenario where 'events' is not bound
        $testApp = new Container();
        $testBridge = new WordPressEventBridge($testApp);
        // Note: register() was NOT called, so singleton not registered
        // We test the callback behavior when bound('events') returns false

        // Create a bridge callback manually
        $callback = (fn(string $hook, string $type): callable => $this->makeBridgeCallback($hook, $type))->call($testBridge, 'wp_loaded', 'action');

        // Should not throw, should return null for action
        $result = $callback();
        $this->assertNull($result);

        // Same for filter — should return original value
        $filterCallback = (fn(string $hook, string $type): callable => $this->makeBridgeCallback($hook, $type))->call($testBridge, 'the_content', 'filter');

        $result = $filterCallback('original');
        $this->assertSame('original', $result);
    }

    /**
     * Test that hasHook() returns correct boolean.
     */
    public function test_has_hook_returns_correct_boolean(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'init' => 'action',
            ],
        ]));

        $this->bridge->boot();

        $this->assertTrue($this->bridge->hasHook('init'));
        $this->assertFalse($this->bridge->hasHook('wp_loaded'));
    }

    /**
     * Test that getRegisteredHooks() returns all registered hooks.
     */
    public function test_get_registered_hooks_returns_all_hooks(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'init'    => 'action',
                'wp_head' => 'action',
                'title'   => 'filter',
            ],
        ]));

        $this->bridge->boot();

        $hooks = $this->bridge->getRegisteredHooks();
        $this->assertCount(3, $hooks);
        $this->assertArrayHasKey('init', $hooks);
        $this->assertArrayHasKey('wp_head', $hooks);
        $this->assertArrayHasKey('title', $hooks);
    }

    /**
     * Test that multiple listeners can modify a filter sequentially.
     *
     * This simulates the WordPress filter chain behavior where multiple
     * callbacks can transform a value in sequence.
     */
    public function test_multiple_filter_listeners_chain(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'the_content' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        // First listener: wrap in div
        $this->app->make('events')->listen('wp:the_content', function (WpHookFired $event): void {
            $event->result = '<div>' . $event->result . '</div>';
        });

        // Second listener: add a class
        $this->app->make('events')->listen('wp:the_content', function (WpHookFired $event): void {
            $event->result = str_replace('<div>', '<div class="wrapped">', $event->result);
        });

        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['the_content']['callback'];
        $result = $callback('<p>Hello</p>');

        $this->assertSame('<div class="wrapped"><p>Hello</p></div>', $result);
    }

    /**
     * Test that filter with multiple arguments passes all args to listener.
     *
     * WordPress filters can have multiple arguments (e.g., wp_insert_post_data
     * has $data and $postarr). All should be available in the event.
     */
    public function test_filter_with_multiple_arguments(): void
    {
        $this->app->instance('config', new \Illuminate\Config\Repository([
            'events.bridge' => [
                'save_post_data' => 'filter',
            ],
        ]));

        $this->bridge->boot();

        $receivedArgs = null;
        $this->app->make('events')->listen('wp:save_post_data', function (WpHookFired $event) use (&$receivedArgs): void {
            $receivedArgs = $event->args;
        });

        $hooks = $this->bridge->getRegisteredHooks();
        $callback = $hooks['save_post_data']['callback'];
        $callback(['post_title' => 'Test'], ['ID' => 123, 'post_title' => 'Test']);

        $this->assertCount(2, $receivedArgs);
        $this->assertSame(['post_title' => 'Test'], $receivedArgs[0]);
        $this->assertSame(['ID' => 123, 'post_title' => 'Test'], $receivedArgs[1]);
    }
}
