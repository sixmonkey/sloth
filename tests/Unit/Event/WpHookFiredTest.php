<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Sloth\Event\WpHookFired;

/**
 * Unit tests for the WpHookFired event class.
 *
 * @since 1.0.0
 */
class WpHookFiredTest extends TestCase
{
    /**
     * Test that action events have correct properties.
     */
    public function test_action_event_properties(): void
    {
        $event = new WpHookFired(
            hook: 'wp_loaded',
            args: [],
            type: 'action',
        );

        $this->assertSame('wp_loaded', $event->hook);
        $this->assertSame([], $event->args);
        $this->assertSame('action', $event->type);
        $this->assertNull($event->result);
    }

    /**
     * Test that filter events have correct properties including result.
     */
    public function test_filter_event_with_result(): void
    {
        $event = new WpHookFired(
            hook: 'the_content',
            args: ['<p>Hello</p>'],
            type: 'filter',
            result: '<p>Hello</p>',
        );

        $this->assertSame('the_content', $event->hook);
        $this->assertSame(['<p>Hello</p>'], $event->args);
        $this->assertSame('filter', $event->type);
        $this->assertSame('<p>Hello</p>', $event->result);
    }

    /**
     * Test that $result is mutable for filter events.
     */
    public function test_filter_result_is_mutable(): void
    {
        $event = new WpHookFired(
            hook: 'the_content',
            args: ['original'],
            type: 'filter',
            result: 'original',
        );

        // Simulate a listener modifying the result
        $event->result = 'modified';

        $this->assertSame('modified', $event->result);
    }

    /**
     * Test that readonly properties cannot be changed.
     */
    public function test_readonly_properties_cannot_be_changed(): void
    {
        $event = new WpHookFired(
            hook: 'init',
            args: ['arg1', 'arg2'],
            type: 'action',
        );

        // Readonly properties should be immutable
        $this->expectException(\Error::class);

        /** @phpstan-ignore-next-line */
        $event->hook = 'wp_loaded';
    }

    /**
     * Test isAction() returns true for action events.
     */
    public function test_is_action_returns_true_for_action(): void
    {
        $event = new WpHookFired('init', [], 'action');

        $this->assertTrue($event->isAction());
        $this->assertFalse($event->isFilter());
    }

    /**
     * Test isFilter() returns true for filter events.
     */
    public function test_is_filter_returns_true_for_filter(): void
    {
        $event = new WpHookFired('the_title', ['title'], 'filter', 'title');

        $this->assertTrue($event->isFilter());
        $this->assertFalse($event->isAction());
    }

    /**
     * Test firstArg() returns the first argument.
     */
    public function test_first_arg_returns_first_argument(): void
    {
        $event = new WpHookFired(
            hook: 'save_post',
            args: [123, (object) ['ID' => 123], true],
            type: 'action',
        );

        $this->assertSame(123, $event->firstArg());
    }

    /**
     * Test firstArg() returns null when no arguments exist.
     */
    public function test_first_arg_returns_null_for_empty_args(): void
    {
        $event = new WpHookFired(
            hook: 'wp_loaded',
            args: [],
            type: 'action',
        );

        $this->assertNull($event->firstArg());
    }

    /**
     * Test filter event with multiple arguments.
     *
     * WordPress filters can pass multiple arguments. The first argument
     * is always the value being filtered, subsequent arguments are context.
     */
    public function test_filter_event_with_multiple_args(): void
    {
        // Example: wp_insert_post_data has $data and $postarr
        $postData = ['post_title' => 'Test'];
        $postarr = ['ID' => 42, 'post_title' => 'Test'];

        $event = new WpHookFired(
            hook: 'wp_insert_post_data',
            args: [$postData, $postarr],
            type: 'filter',
            result: $postData,
        );

        $this->assertSame($postData, $event->firstArg());
        $this->assertCount(2, $event->args);
        $this->assertSame($postarr, $event->args[1]);
    }
}
