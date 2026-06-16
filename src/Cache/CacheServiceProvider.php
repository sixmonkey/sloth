<?php

declare(strict_types=1);
namespace Sloth\Cache;

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Override;
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
 * @see WordPressTransientsStore For the WP Transients driver
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
    #[Override]
    public function register(): void
    {
        $this->app->singleton('cache', fn ($app): CacheManager => new CacheManager($app));

        $this->app->singleton('cache.store', fn ($app) => $app['cache']->driver());

        $this->app->singleton('memcached.connector', fn (): \Illuminate\Cache\MemcachedConnector => new \Illuminate\Cache\MemcachedConnector());
    }

    /**
     * Boot the Cache service.
     *
     * Configures the file cache driver with the theme cache path,
     * and registers the WordPress transients driver.
     *
     * The default driver can be overridden by publishing and editing
     * the cache config file:
     *
     * ```bash
     * wp sloth vendor:publish --provider="Sloth\Cache\CacheServiceProvider" --tag=config
     * ```
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        // Merge driver and prefix defaults into Laravel's cache config.
        // If the user publishes app/config/cache.php, their values take precedence.
        $this->mergeConfigFrom(__DIR__ . '/config/cache.php', 'cache');

        // Publish the config file for customization
        $this->publishes([
            __DIR__ . '/config/cache.php' => app()->configPath('cache.php'),
        ], 'config');

        // Set the file store path — this can't come from the published config
        // because the path is resolved at runtime from the application.
        $this->app['config']->set('cache.stores.file', [
            'driver' => 'file',
            'path'   => $this->app->cachePath(),
        ]);

        $this->app['config']->set('cache.stores.array', [
            'driver'    => 'array',
            'serialize' => false,
        ]);

        // Register WordPress transients as a custom cache driver
        $this->app['cache']->extend(
            'wp-transients',
            fn (): Repository => new Repository(
                new WordPressTransientStore(config('cache.prefix', 'sloth_')),
            ),
        );
    }
}
