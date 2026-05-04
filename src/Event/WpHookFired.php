<?php

declare(strict_types=1);

namespace Sloth\Event;

/**
 * Event object representing a WordPress hook (action or filter) that was fired.
 *
 * This class is used by the WordPressEventBridge to transport WordPress hook
 * invocations into the Laravel event system. Each time a bridged WordPress
 * hook fires (via do_action or apply_filters), a WpHookFired instance is
 * created and dispatched as a Laravel event with the name `wp:{hook}`.
 *
 * The `$type` property distinguishes between actions and filters:
 * - 'action': Fire-and-forget. Listeners can react but cannot modify any value.
 * - 'filter': Bidirectional. Listeners can set `$result` to modify the return
 *   value of the WordPress filter.
 *
 * Usage examples:
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
 * @since 1.0.0
 */
class WpHookFired
{
    /**
     * Create a new WordPress hook fired event.
     *
     * @param string $hook The WordPress hook name (e.g., 'wp_loaded', 'the_content').
     * @param array<int, mixed> $args The arguments that were passed to the WordPress hook.
     *                                For actions these are the do_action() arguments.
     *                                For filters these are the apply_filters() arguments
     *                                (with the filtered value always being the first element).
     * @param string $type The hook type: 'action' or 'filter'.
     * @param mixed $result The current filter result. Only relevant for filter hooks;
     *                      listeners can mutate this property to change the value returned
     *                      by apply_filters(). For action hooks this is always null.
     */
    public function __construct(
        public readonly string $hook,
        public readonly array $args,
        public readonly string $type,
        public mixed $result = null,
    ) {}

    /**
     * Determine whether this event represents a WordPress action.
     *
     * @since 1.0.0
     */
    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    /**
     * Determine whether this event represents a WordPress filter.
     *
     * @since 1.0.0
     */
    public function isFilter(): bool
    {
        return $this->type === 'filter';
    }

    /**
     * Get the first argument passed to the hook.
     *
     * For filters this is the value being filtered. For actions this is
     * the first argument passed to do_action().
     *
     * @since 1.0.0
     */
    public function firstArg(): mixed
    {
        return $this->args[0] ?? null;
    }
}
