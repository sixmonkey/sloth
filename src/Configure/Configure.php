<?php

declare(strict_types=1);

namespace Sloth\Configure;

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
     * Legacy boot — config is now loaded by Sloth::loadConfigFiles().
     * Kept for backwards compatibility.
     *
     * @since 1.0.0
     */
    public static function boot(): void
    {
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