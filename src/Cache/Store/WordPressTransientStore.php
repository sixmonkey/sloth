<?php

declare(strict_types=1);

namespace Sloth\Cache\Store;

use Illuminate\Cache\TaggableStore;
use Illuminate\Contracts\Cache\Store;

/**
 * WordPress Transients Cache Store.
 *
 * Implements the Laravel Cache Store contract using WordPress transients,
 * allowing themes that prefer not to use filesystem caching to store
 * cache data in the WordPress database via the Transients API.
 *
 * ## Usage
 *
 * Use via the Cache facade with the 'wp-transients' driver:
 *
 * ```php
 * Cache::driver('wp-transients')->rememberForever('key', fn() => expensive());
 * ```
 *
 * Or set as default in CacheServiceProvider if preferred over file cache.
 *
 * ## Prefix
 *
 * All keys are prefixed with 'sloth_' to avoid collisions with other
 * plugins that use WordPress transients.
 *
 * ## Expiry
 *
 * WordPress transients use seconds for expiry. Passing 0 stores the value
 * indefinitely (no expiry). This maps to Laravel's `forever()` behaviour.
 *
 * @since 1.0.0
 * @see \Illuminate\Contracts\Cache\Store
 */
class WordPressTransientStore extends TaggableStore implements Store
{
    /**
     * Key prefix to avoid collisions with other plugins.
     *
     * @since 1.0.0
     */
    protected string $prefix = 'sloth_';

    /**
     * Retrieve an item from the cache by key.
     *
     * Returns null if the item does not exist or has expired.
     *
     * @since 1.0.0
     *
     * @param string $key The cache key.
     * @return mixed The cached value or null.
     */
    public function get($key): mixed
    {
        $value = \get_transient($this->prefix . $key);

        return $value === false ? null : $value;
    }

    /**
     * Retrieve multiple items from the cache by key.
     *
     * Items not found in the cache will have a null value.
     *
     * @since 1.0.0
     *
     * @param array<string> $keys The cache keys.
     * @return array<string, mixed> Key-value pairs.
     */
    public function many(array $keys): array
    {
        return array_combine($keys, array_map(fn($key) => $this->get($key), $keys));
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @since 1.0.0
     *
     * @param string $key     The cache key.
     * @param mixed  $value   The value to cache.
     * @param int    $seconds Number of seconds until expiry. 0 = no expiry.
     * @return bool True on success.
     */
    public function put($key, $value, $seconds): bool
    {
        return \set_transient($this->prefix . $key, $value, $seconds);
    }

    /**
     * Store multiple items in the cache for a given number of seconds.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $values  Key-value pairs to cache.
     * @param int                  $seconds Number of seconds until expiry.
     * @return bool True if all items were stored successfully.
     */
    public function putMany(array $values, $seconds): bool
    {
        return array_reduce(
            array_keys($values),
            fn($carry, $key) => $carry && $this->put($key, $values[$key], $seconds),
            true
        );
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @since 1.0.0
     *
     * @param string $key   The cache key.
     * @param int    $value The amount to increment by.
     * @return int|bool The new value or false on failure.
     */
    public function increment($key, $value = 1): int|bool
    {
        $current = $this->get($key) ?? 0;
        $new     = $current + $value;

        return $this->put($key, $new, 0) ? $new : false;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @since 1.0.0
     *
     * @param string $key   The cache key.
     * @param int    $value The amount to decrement by.
     * @return int|bool The new value or false on failure.
     */
    public function decrement($key, $value = 1): int|bool
    {
        return $this->increment($key, -$value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @since 1.0.0
     *
     * @param string $key   The cache key.
     * @param mixed  $value The value to cache.
     * @return bool True on success.
     */
    public function forever($key, $value): bool
    {
        return $this->put($key, $value, 0);
    }

    /**
     * Remove an item from the cache.
     *
     * @since 1.0.0
     *
     * @param string $key The cache key.
     * @return bool True on success.
     */
    public function forget($key): bool
    {
        return \delete_transient($this->prefix . $key);
    }

    /**
     * Remove all items from the cache.
     *
     * Deletes all transients with the Sloth prefix from the WordPress database.
     *
     * @since 1.0.0
     *
     * @return bool True on success.
     */
    public function flush(): bool
    {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->prefix . '%',
                '_transient_timeout_' . $this->prefix . '%'
            )
        );

        return true;
    }

    /**
     * Get the cache key prefix.
     *
     * @since 1.0.0
     *
     * @return string The prefix string.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }
}
