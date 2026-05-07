<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

/**
 * Base Service Provider for Sloth framework.
 *
 * This is the abstract base class for all Sloth service providers.
 * It extends Laravel's ServiceProvider and adds a declarative hook
 * registration system via `getHooks()` and `getFilters()`.
 *
 * Instead of calling add_action()/add_filter() directly in boot(),
 * providers should return their hooks/filters from these methods.
 * The framework automatically registers them during boot with the
 * correct priority and argument count.
 *
 * ## Why use getHooks()/getFilters() instead of add_action() directly?
 *
 * 1. **Separation of concerns** — hook registration is declarative and
 *    centralized, not scattered throughout boot() code
 * 2. **Testability** — hooks can be inspected without executing them
 * 3. **Framework control** — Sloth manages registration order and timing
 * 4. **Consistency** — all providers follow the same pattern
 *
 * ## Hook Registration Formats
 *
 * Both methods support three formats per hook name:
 *
 * ```php
 * // 1. Single callable
 * 'init' => fn() => $this->doSomething(),
 *
 * // 2. Multiple callbacks for the same hook
 * 'init' => [
 *     fn() => $this->stepOne(),
 *     fn() => $this->stepTwo(),
 * ],
 *
 * // 3. With explicit priority
 * 'init' => ['callback' => fn() => $this->doSomething(), 'priority' => 20],
 * ```
 *
 * ## When to use EventBridge instead
 *
 * For provider-local hooks (only this provider cares), use getHooks()/getFilters().
 * For shared WordPress hooks that multiple components might listen to
 * (e.g., the_content, wp_loaded), prefer the EventBridge:
 *
 * ```php
 * public function boot(): void
 * {
 *     Event::listen('wp:the_content', function (WpHookFired $event) {
 *         $event->result = transform($event->result);
 *     });
 * }
 * ```
 *
 * All framework service providers should extend this class.
 *
 * @since 1.0.0
 * @see IlluminateServiceProvider For the base Laravel implementation
 * @see \Sloth\Event\WordPressEventBridge For the WordPress event bridge
 *
 * @example
 * ```php
 * class MyServiceProvider extends ServiceProvider
 * {
 *     public function register(): void
 *     {
 *         // Bind services into the container
 *         $this->app->singleton('my-service', fn() => new MyService());
 *     }
 *
 *     public function boot(): void
 *     {
 *         // Boot-time setup (optional)
 *         // For shared hooks, use the EventBridge here
 *     }
 *
 *     public function getHooks(): array
 *     {
 *         return [
 *             // Register a callback on the 'init' action
 *             'init' => fn() => $this->registerPostTypes(),
 *
 *             // Multiple callbacks with different priorities
 *             'wp_loaded' => [
 *                 ['callback' => fn() => $this->earlySetup(), 'priority' => 10],
 *                 ['callback' => fn() => $this->lateSetup(), 'priority' => 20],
 *             ],
 *         ];
 *     }
 *
 *     public function getFilters(): array
 *     {
 *         return [
 *             // Modify the_content filter
 *             'the_content' => fn(string $content) => $this->transform($content),
 *
 *             // Modify admin footer text
 *             'admin_footer_text' => ['callback' => fn() => 'My Theme', 'priority' => 100],
 *         ];
 *     }
 * }
 * ```
 */
abstract class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Returns WordPress actions this provider wants to register.
     *
     * The framework automatically calls add_action() for each entry during
     * the boot phase. Providers should NEVER call add_action() directly —
     * always use this method to ensure consistent registration.
     *
     * ## Supported Formats
     *
     * ```php
     * // Single callable (default priority: 10)
     * 'init' => fn() => $this->doSomething(),
     *
     * // Multiple callbacks for the same hook
     * 'init' => [
     *     fn() => $this->stepOne(),
     *     fn() => $this->stepTwo(),
     * ],
     *
     * // With explicit priority
     * 'init' => ['callback' => fn() => $this->doSomething(), 'priority' => 20],
     *
     * // Multiple callbacks with different priorities
     * 'init' => [
     *     ['callback' => fn() => $this->early(), 'priority' => 5],
     *     ['callback' => fn() => $this->late(), 'priority' => 20],
     * ],
     * ```
     *
     * ## When to use EventBridge instead
     *
     * Use getHooks() for provider-local actions. For shared lifecycle hooks
     * that multiple components might need (e.g., wp_loaded, template_redirect),
     * consider using the EventBridge in boot():
     *
     * ```php
     * Event::listen('wp:wp_loaded', fn() => $this->doSomething());
     * ```
     *
     * @return array<string, callable|array{callback: callable, priority: int}|array<callable|array{callback: callable, priority: int}>>
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [];
    }

    /**
     * Returns WordPress filters this provider wants to register.
     *
     * The framework automatically calls add_filter() for each entry during
     * the boot phase. Providers should NEVER call add_filter() directly —
     * always use this method to ensure consistent registration.
     *
     * ## Supported Formats
     *
     * ```php
     * // Single callable (default priority: 10)
     * 'the_content' => fn(string $content) => strtoupper($content),
     *
     * // Multiple callbacks for the same filter
     * 'the_content' => [
     *     fn(string $content) => $this->stepOne($content),
     *     fn(string $content) => $this->stepTwo($content),
     * ],
     *
     * // With explicit priority
     * 'the_content' => ['callback' => fn(string $c) => $this->transform($c), 'priority' => 20],
     *
     * // Multiple callbacks with different priorities
     * 'the_content' => [
     *     ['callback' => fn(string $c) => $this->early($c), 'priority' => 5],
     *     ['callback' => fn(string $c) => $this->late($c), 'priority' => 20],
     * ],
     * ```
     *
     * ## Filter Callback Signature
     *
     * Filter callbacks should accept the filtered value as the first argument
     * and return the modified value:
     *
     * ```php
     * 'the_title' => fn(string $title) => 'Prefixed: ' . $title,
     * ```
     *
     * ## When to use EventBridge instead
     *
     * Use getFilters() for provider-local filters. For widely-used WordPress
     * filters that multiple components might modify (e.g., the_content, body_class),
     * consider using the EventBridge in boot() for bidirectional filter support:
     *
     * ```php
     * Event::listen('wp:the_content', function (WpHookFired $event) {
     *     $event->result = transform($event->result);
     * });
     * ```
     *
     * @return array<string, callable|array{callback: callable, priority: int}|array<callable|array{callback: callable, priority: int}>>
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        return [];
    }

    /**
     * Boot the service provider.
     *
     * Override in subclasses to perform boot-time setup such as
     * registering view composers, loading config files, or setting
     * up integrations that require other providers to be registered.
     *
     * Called after all providers have been registered.
     *
     * @since 1.0.0
     */
    public function boot(): void {}

    /**
     * Handles calls to undefined methods.
     *
     * @param string $method The method name that was called.
     * @param array<int, mixed> $parameters The parameters passed to the method.
     * @return mixed
     * @throws \Exception If the method is not defined.
     * @since 1.0.0
     *
     */
    public function __call(string $method, array $parameters): mixed
    {
        throw new \Exception(sprintf(
            'Call to undefined method [%s::%s]',
            static::class,
            $method
        ));
    }
}
