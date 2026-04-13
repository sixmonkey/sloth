<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Illuminate\Config\Repository;
use Sloth\Core\Application;
use Sloth\Facades\Facade;
use Sloth\Singleton\Singleton;

/**
 * Configure class for managing application configuration.
 *
 * This is a backwards-compatible wrapper around Laravel's illuminate/config.
 * Existing themes calling Configure::read() and Configure::write() continue to work unchanged.
 *
 * @since 1.0.0
 * @extends Singleton
 */
class Configure extends Singleton
{
    /**
     * Initialize the config container and facade.
     *
     * This must be called early in bootstrap.php, before any config files
     * that use Configure::read(). Sets up the Laravel config repository and
     * facade system so config() helper works immediately.
     *
     * @since 1.0.0
     */
    public static function boot(): void
    {
        $container = new Application();
        $container->singleton('config', static fn() => new Repository([]));

        Facade::setFacadeApplication($container);

        $configPath = defined('DIR_CFG') ? DIR_CFG : null;
        if ($configPath && is_dir($configPath)) {
            foreach (glob($configPath . '*.php') as $file) {
                require $file;
            }
        }
    }

    /**
     * Read a value from the configuration.
     *
     * @since 1.0.0
     *
     * @param string|null $var Variable to obtain. Use '.' to access array elements.
     *
     * @return mixed Value stored in configure, or null if not found.
     */
    public static function read(?string $var = null): mixed
    {
        if ($var === null) {
            return config()->all();
        }

        return config($var);
    }

    /**
     * Write a value to the configuration.
     *
     * @since 1.0.0
     *
     * @param string|array<string, mixed> $config The key to write (dot notation supported) or array of keys/values
     * @param mixed                       $value  Value to set (ignored if $config is array)
     *
     * @return bool True if write was successful
     */
    public static function write(string|array $config, mixed $value = null): bool
    {
        if (is_array($config)) {
            foreach ($config as $key => $val) {
                config([$key => $val]);
            }

            return true;
        }

        config([$config => $value]);

        return true;
    }

    /**
     * Read and delete a variable from Configure.
     *
     * @since 1.0.0
     *
     * @param string $var The key to read and remove (dot notation supported)
     *
     * @return mixed|null
     */
    public static function consume(string $var): mixed
    {
        $value = config($var);
        config([$var => null]);

        return $value;
    }

    /**
     * Check if a variable is set in Configure.
     *
     * @since 1.0.0
     *
     * @param string $var Variable name to check for (dot notation supported)
     *
     * @return bool True if variable is set
     */
    public static function check(string $var): bool
    {
        if ($var === '' || $var === '0') {
            return false;
        }

        return config($var) !== null;
    }

    /**
     * Delete a variable from Configure.
     *
     * @since 1.0.0
     *
     * @param string $var The var to be deleted (dot notation supported)
     */
    public static function delete(string $var): void
    {
        config([$var => null]);
    }

    /**
     * Debug all set variables.
     *
     * @since 1.0.0
     */
    public static function debug(): void
    {
        debug(config()->all());
    }
}
