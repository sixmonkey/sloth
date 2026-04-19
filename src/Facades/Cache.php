<?php

declare(strict_types=1);

namespace Sloth\Facades;

use Illuminate\Cache\CacheManager;

/**
 * Cache Facade — static access to the Cache service.
 *
 * Provides a convenient static interface to `illuminate/cache`,
 * supporting multiple cache drivers (file, array, wp-transients).
 *
 * ## Usage
 *
 * ```php
 * use Sloth\Facades\Cache;
 *
 * // Store a value forever
 * Cache::forever('key', $value);
 *
 * // Store a value with expiry (seconds)
 * Cache::put('key', $value, 3600);
 *
 * // Remember — only compute if not cached
 * $models = Cache::rememberForever('sloth.models', fn() => ModelsResolver::collect());
 *
 * // Retrieve
 * $value = Cache::get('key');
 *
 * // Check existence
 * Cache::has('key');
 *
 * // Delete
 * Cache::forget('key');
 *
 * // Clear all
 * Cache::flush();
 *
 * // Use a specific driver
 * Cache::driver('wp-transients')->rememberForever('key', fn() => expensive());
 * ```
 *
 * ## Drivers
 *
 * - `file` (default) — stores cache in theme/cache/
 * - `array` — in-memory only, not persisted between requests
 * - `wp-transients` — uses WordPress transients API
 *
 * ## Class alias
 *
 * Registered in `Application::$classAliases` as `'Cache'`.
 *
 * @since 1.0.0
 * @see \Illuminate\Cache\CacheManager For all available methods
 * @see \Sloth\Cache\CacheServiceProvider For container registration
 *
 * @method static mixed    get(string $key, mixed $default = null)                          Retrieve an item from the cache.
 * @method static bool     has(string $key)                                                 Determine if an item exists in the cache.
 * @method static bool     missing(string $key)                                             Determine if an item is missing from the cache.
 * @method static bool     put(string $key, mixed $value, int $seconds)                    Store an item in the cache for a given number of seconds.
 * @method static bool     forever(string $key, mixed $value)                              Store an item in the cache indefinitely.
 * @method static mixed    remember(string $key, int $seconds, \Closure $callback)         Get an item from the cache, or execute the given Closure and store the result.
 * @method static mixed    rememberForever(string $key, \Closure $callback)                Get an item from the cache, or execute the given Closure and store the result forever.
 * @method static bool     forget(string $key)                                             Remove an item from the cache.
 * @method static bool     flush()                                                         Remove all items from the cache.
 * @method static \Illuminate\Cache\Repository driver(string $driver = null)               Get a cache driver instance.
 */
class Cache extends Facade
{
    /**
     * Return the container binding key for the Cache service.
     *
     * The `Illuminate\Cache\CacheManager` instance is registered
     * under the `'cache'` key by `CacheServiceProvider`.
     *
     * @return string The container binding key.
     * @since 1.0.0
     *
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return 'cache';
    }
}
