<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Sloth\Configure\Configure;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Configure component.
 *
 * Registers the Configure singleton and loads all project config files
 * from app/config/ into the Laravel config repository.
 *
 * ## Config loading order
 *
 * 1. `app/config/app.config.php` — loaded first via require_once.
 *    May contain procedural code (Configure::write, define).
 *    Already loaded by bootstrap.php in most setups — require_once
 *    is a no-op in that case.
 *
 * 2. All other `app/config/*.php` files — loaded as Laravel config
 *    arrays (must return an array). Each filename becomes a config key:
 *    `theme.php` → `config('theme')`.
 *
 *    Uses require_once to prevent double-execution of files already
 *    loaded by bootstrap.php (e.g. salts.php, app.config.php).
 *
 * @since 1.0.0
 */
class ConfigureServiceProvider extends ServiceProvider
{
    /**
     * Register the Configure singleton.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'configure',
            fn($container): Configure => Configure::getInstance()
        );
    }

    /**
     * Load all project config files into the Laravel config repository.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $configPath = $this->app->path('config', 'app');

        if (is_dir($configPath)) {
            // Load app.config.php first — procedural, may call Configure::write()
            $appConfig = $configPath . '/app.config.php';
            if (file_exists($appConfig)) {
                require_once $appConfig;
            }

            // Load remaining *.php files as Laravel config arrays
            foreach (glob($configPath . '/*.php') as $file) {
                if (realpath($file) === realpath($appConfig)) {
                    continue; // already loaded above
                }

                $value = require_once $file;

                // Only set if file returns an array — skip procedural files
                // (require_once returns true for already-loaded files)
                if (is_array($value)) {
                    $key = basename($file, '.php');
                    $this->app['config']->set($key, $value);
                }
            }
        }

        // Flush all Configure::write() values into the Laravel config repository.
        // This ensures that theme code using Configure::write() before boot
        // is visible to providers that read config() after boot.
        //
        // Note: set() does NOT overwrite keys already set by Laravel config files —
        // it only fills in keys that are not yet present.
        foreach (Configure::read() as $key => $value) {
            if (!$this->app['config']->has($key)) {
                $this->app['config']->set($key, $value);
            }
        }
    }
}
