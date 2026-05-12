<?php

declare(strict_types=1);

/**
 * Cache Configuration.
 *
 * Override the default cache driver and prefix used by Sloth.
 *
 * Available drivers:
 *   - 'file'          — stores cache in theme/cache/Cache/ (default)
 *   - 'wp-transients' — stores cache in the WordPress database
 *   - 'array'         — in-memory only, not persisted between requests
 *
 * Publish this file to your project:
 *
 * ```bash
 * wp sloth vendor:publish --provider="Sloth\Cache\CacheServiceProvider" --tag=config
 * ```
 *
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Cache Driver
    |--------------------------------------------------------------------------
    |
    | The default cache driver used by Cache::put(), Cache::remember(), etc.
    |
    | 'file' stores cache in theme/cache/Cache/ — fast and zero-config.
    | 'wp-transients' stores in the WordPress database — survives deployments
    | and works on hosts where filesystem caching is restricted.
    |
    */

    'driver' => 'file',

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix applied to all cache keys to avoid collisions with other plugins
    | when using the wp-transients driver.
    |
    */

    'prefix' => 'sloth_',
];
