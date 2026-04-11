<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Cake\Utility\Hash;
use Sloth\Singleton\Singleton;

/**
 * Configure class for managing application configuration.
 *
 * @since 1.0.0
 * @extends Singleton
 */
class Configure extends Singleton
{
    /**
     * Array of values currently stored in Configure.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected static array $_values = [
        'debug' => 0,
    ];

    /**
     * Write a value to the configuration.
     *
     * @since 1.0.0
     *
     * @param string|array<string, mixed> $config The key to write (dot notation supported) or array of keys/values
     * @param mixed                      $value  Value to set (ignored if $config is array)
     *
     * @return bool True if write was successful
     */
    public static function write(string|array $config, mixed $value = null): bool
    {
        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $val) {
            static::$_values = Hash::insert(static::$_values, $name, $val);
        }

        return true;
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
            return static::$_values;
        }

        return Hash::get(static::$_values, $var);
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
        $simple = !str_contains($var, '.');
        if ($simple && !isset(static::$_values[$var])) {
            return null;
        }

        if ($simple) {
            $value = static::$_values[$var];
            unset(static::$_values[$var]);

            return $value;
        }

        $value = Hash::get(static::$_values, $var);
        static::$_values = Hash::remove(static::$_values, $var);

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

        return Hash::get(static::$_values, $var) !== null;
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
        static::$_values = Hash::remove(static::$_values, $var);
    }

    /**
     * Boot Configure from environment variables.
     *
     * @since 1.0.0
     */
    public static function boot(): void
    {
        foreach ($_ENV as $k => $v) {
            self::write('ENV.' . $k, $v);
        }
    }

    /**
     * Debug all set variables.
     *
     * @since 1.0.0
     */
    public static function debug(): void
    {
        debug(self::$_values);
    }
}
