<?php

declare(strict_types=1);
namespace Sloth\Context;

/**
 * Base class for context providers.
 *
 * A context provider resolves a single key in the Twig template context.
 * Providers are registered with Context::register() and resolved lazily —
 * the resolve() method is only called when the key is actually accessed.
 *
 * ## Creating a provider
 *
 * ```php
 * // app/Context/MyProvider.php
 * class MyProvider extends ContextProvider
 * {
 *     public string $key = 'my_data';
 *
 *     public function resolve(): array
 *     {
 *         return ['foo' => 'bar'];
 *     }
 * }
 * ```
 *
 * ## Conditional providers
 *
 * Override shouldResolve() to conditionally include the key:
 *
 * ```php
 * public function shouldResolve(): bool
 * {
 *     return is_single();
 * }
 * ```
 *
 * If shouldResolve() returns false, the key is not added to the context.
 *
 * ## Registration
 *
 * Drop the class in app/Context/ or theme/Context/ for auto-discovery,
 * or register manually via:
 *
 * ```php
 * app('context')->register(new MyProvider());
 * ```
 *
 * @since 1.0.0
 */
abstract class ContextProvider
{
    /**
     * The key under which this provider's value is available in Twig.
     *
     * @since 1.0.0
     */
    abstract public function key(): string;

    /**
     * Resolve and return the value for this context key.
     *
     * Called lazily — only when the key is accessed in the template.
     *
     * @since 1.0.0
     */
    abstract public function resolve(): mixed;

    /**
     * Whether this provider should be included in the context.
     *
     * Override to conditionally include a key — for example, post context
     * only makes sense on single post pages:
     *
     * ```php
     * public function shouldResolve(): bool
     * {
     *     return is_single() || is_page();
     * }
     * ```
     *
     * @since 1.0.0
     */
    public function shouldResolve(): bool
    {
        return true;
    }
}
