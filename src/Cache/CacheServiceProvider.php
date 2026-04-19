<?php

declare(strict_types=1);

namespace Sloth\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Sloth\Cache\Store\WordPressTransientStore;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Cache component.
 *
 * Registers `Illuminate\Cache\CacheManager` in the container under the
 * `'cache'` key, and adds a `'wp-transients'` driver backed by
 * WordPress transients.
 *
 * ## Default driver: file
 *
 * Cache files are stored in `theme/cache/Cache/` — the same directory
 * that is auto-created by `Application::registerBasePaths()`.
 *
 * ## Available drivers
 *
 * - `file` (default) — stores cache in theme/cache/
 * - `array` — in-memory only, not persisted between requests
 * - `wp-transients` — uses WordPress transients API (stored in DB)
 *
 * ## Switching to WP Transients
 *
 * Override the default driver in a ServiceProvider:
 *
 * ```php
 * app('cache')->setDefaultDriver('wp-transients');
 * ```
 *
 * Or use a specific driver per call:
 *
 * ```php
 * Cache::driver('wp-transients')->rememberForever('key', fn() => expensive());
 * ```
 *
 * ## Usage in ClassResolver
 *
 * ```php
 * public static function resolve(): Collection
 * {
 *     if (app()->isLocal()) {
 *         return static::collectClasses();
 *     }
 *     return Cache::rememberForever(static::$cacheKey, fn() => static::collectClasses());
 * }
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Cache For the static Facade interface
 * @see \Sloth\Cache\WordPressTransientsStore For the WP Transients driver
 */
class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register the Cache service in the container.
     *
     * Binds `Illuminate\Cache\CacheManager` as a singleton under the
     * `'cache'` key — the same key used by Laravel itself, ensuring
     * compatibility with any illuminate/* package that resolves `'cache'`.
     *
     * Also registers `'cache.store'` as the default driver instance,
     * and extends the manager with the `'wp-transients'` driver.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton('cache', function ($app) {
            $manager = new CacheManager($app);
            return $manager;
        });

        $this->app->singleton('cache.store', fn($app) => $app['cache']->driver());

        $this->app->singleton('memcached.connector', fn() => new \Illuminate\Cache\MemcachedConnector());
    }

    /**
     * Boot the Cache service.
     *
     * Configures the file cache driver with the theme cache path,
     * and registers the WordPress transients driver.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Configure the cache repository in the Laravel config format
        $cachePath = $this->app->path('Cache', 'cache');

        $this->app['config']->set('cache', [
            'default' => 'file',
            'stores'  => [
                'file' => [
                    'driver' => 'file',
                    'path'   => $cachePath,
                ],
                'array' => [
                    'driver'    => 'array',
                    'serialize' => false,
                ],
            ],
            'prefix' => 'sloth',
        ]);

        // Register WordPress transients as a custom cache driver
        $this->app['cache']->extend(
            'wp-transients',
            fn()
        => new Repository(new WordPressTransientStore())
        );
    }
}
