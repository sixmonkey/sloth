<?php

declare(strict_types=1);
namespace Sloth\Context;

use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Sloth\Core\Application;
use Traversable;

/**
 * Lazy, extensible template context for Twig templates.
 *
 * Context values are resolved on first access — not upfront. This means
 * expensive calls like get_bloginfo(), get_queried_object() or Eloquent queries
 * are only made when the template actually needs the value.
 *
 * ## Built-in keys
 *
 * | Key | Content |
 * |-----|---------|
 * | `site` | Site information (name, url, description, …) |
 * | `globals` | Global URLs (home_url, theme_url, images_url) |
 * | `sloth` | Sloth internals (current_layout) |
 * | `wp_title` | Current page title |
 * | `post` | Current post model (single/page only) |
 * | `taxonomy` | Current taxonomy term (taxonomy archive only) |
 * | `author` | Current author (author archive only) |
 * | `options` | Options accessor — {{ options.my_field }} |
 *
 * ## Registering custom providers
 *
 * Drop a class extending ContextProvider in app/Context/ or theme/Context/
 * for auto-discovery, or register manually:
 *
 * ```php
 * app('context')->register(new MyContextProvider());
 * ```
 *
 * ## Usage in Twig
 *
 * ```twig
 * {{ site.name }}
 * {{ post.title }}
 * {{ options.primary_color }}
 * {{ my_custom_key.value }}
 * ```
 *
 * ## Backwards compatibility
 *
 * getContext() returns $this — which implements ArrayAccess and IteratorAggregate.
 * Code that uses $context['key'] or iterates over the context continues to work.
 * Code that calls array_merge() on the context must be updated to use
 * array_merge($context->toArray(), $extra) instead.
 *
 * @since 1.0.0
 * @implements ArrayAccess<string, mixed>
 * @implements IteratorAggregate<string, mixed>
 */
class Context implements ArrayAccess, IteratorAggregate
{
    /**
     * Registered context providers.
     *
     * @var array<string, ContextProvider>
     */
    protected array $providers = [];

    /**
     * Resolved values cache.
     *
     * @var array<string, mixed>
     */
    protected array $resolved = [];

    /**
     * Statically set values — bypass provider resolution.
     *
     * @var array<string, mixed>
     */
    protected array $static = [];

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct(private readonly Application $app)
    {
    }

    /**
     * Register a context provider.
     *
     * The provider's key() determines where the value appears in Twig.
     * If a provider with the same key already exists, it is replaced.
     *
     * ```php
     * app('context')->register(new MyContextProvider());
     * ```
     *
     * @since 1.0.0
     */
    public function register(ContextProvider $provider): static
    {
        $this->providers[$provider->key()] = $provider;

        return $this;
    }

    /**
     * Set a static value directly — bypasses provider resolution.
     *
     * Useful for one-off values that don't warrant a full provider:
     *
     * ```php
     * app('context')->set('current_step', 3);
     * ```
     *
     * @since 1.0.0
     */
    public function set(string $key, mixed $value): static
    {
        $this->static[$key] = $value;

        return $this;
    }

    /**
     * Get all context as a plain array.
     *
     * Resolves all providers whose shouldResolve() returns true.
     * Used when the full context is needed at once — e.g. for passing
     * to Twig::render() or array_merge().
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function toArray(): array
    {
        $context = $this->static;

        foreach ($this->providers as $key => $provider) {
            if ($provider->shouldResolve()) {
                $context[$key] = $this->resolveProvider($provider);

                // Post type key alias (e.g. 'project' alongside 'post')
                if ($key === 'post' && isset(get_queried_object()->post_type)) {
                    $context[get_queried_object()->post_type] = $context[$key];
                }

                // Taxonomy slug alias
                if ($key === 'taxonomy') {
                    global $taxonomy;
                    if ($taxonomy) {
                        $context[$taxonomy] = $context[$key];
                    }
                }

                // Author alias
                if ($key === 'author') {
                    $context['user'] = $context[$key];
                }
            }
        }

        $this->app->instance('sloth.context', $context);

        return $context;
    }

    /**
     * Get the full context — backwards compatible entry point.
     *
     * Returns $this which implements ArrayAccess and IteratorAggregate.
     * For a plain array, call toArray() instead.
     *
     * @since 1.0.0
     */
    public function getContext(): static
    {
        return $this;
    }

    /**
     * Resolve a provider and cache the result.
     *
     * @since 1.0.0
     */
    protected function resolveProvider(ContextProvider $provider): mixed
    {
        $key = $provider->key();

        if (!array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $provider->resolve();
        }

        return $this->resolved[$key];
    }

    // -------------------------------------------------------------------------
    // ArrayAccess
    // -------------------------------------------------------------------------

    /**
     * @since 1.0.0
     */
    public function offsetExists(mixed $offset): bool
    {
        if (isset($this->static[$offset])) {
            return true;
        }

        if (isset($this->providers[$offset])) {
            return $this->providers[$offset]->shouldResolve();
        }

        return false;
    }

    /**
     * Resolves the provider for $offset on first access.
     *
     * @since 1.0.0
     */
    public function offsetGet(mixed $offset): mixed
    {
        if (array_key_exists($offset, $this->static)) {
            return $this->static[$offset];
        }

        if (isset($this->providers[$offset]) && $this->providers[$offset]->shouldResolve()) {
            return $this->resolveProvider($this->providers[$offset]);
        }

        return null;
    }

    /**
     * @since 1.0.0
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->static[$offset] = $value;
    }

    /**
     * @since 1.0.0
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->static[$offset], $this->providers[$offset], $this->resolved[$offset]);
    }

    // -------------------------------------------------------------------------
    // IteratorAggregate
    // -------------------------------------------------------------------------

    /**
     * Iterates over all resolved context values.
     *
     * @since 1.0.0
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }
}
