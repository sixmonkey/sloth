<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Sloth\Facades\Facade;

/**
 * Configure — standalone key-value store with dot-notation support.
 *
 * This is a backwards-compatible store for theme configuration.
 * It intentionally does NOT proxy to Laravel's config() helper —
 * that would create circular dependencies during boot.
 *
 * ## Migration path
 *
 * Theme code using Configure::write/read will continue to work.
 * Framework internals have been migrated to config() directly.
 * Use folivoro/shift to migrate theme code — see MIGRATE.md.
 *
 * @since 1.0.0
 */
class Configure
{
    /**
     * All stored configuration values.
     *
     * @var array<string, mixed>
     */
    private static array $values = [];

    /**
     * Write one or more values to the store.
     *
     * Supports dot-notation keys and array of key-value pairs:
     *
     * ```php
     * Configure::write('theme.foo', 'bar');
     * Configure::write(['theme.foo' => 'bar', 'theme.baz' => 'qux']);
     * ```
     *
     * @param string|array<string, mixed> $config
     * @param mixed                       $value
     * @since 1.0.0
     */
    public static function write(string|array $config, mixed $value = null): bool
    {
        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $key => $val) {
            data_set(self::$values, $key, $val);
        }

        return true;
    }

    /**
     * Read a value from the store.
     *
     * Returns all values when called without arguments.
     *
     * @since 1.0.0
     */
    public static function read(?string $var = null, mixed $default = null): mixed
    {
        if ($var === null) {
            return self::$values;
        }

        // If the app is booted, prefer config() — it may have been enriched
        // by other providers. Fall back to internal store during early boot.
        if (Facade::getFacadeApplication()?->bound('config')) {
            $fromConfig = config($var);
            if ($fromConfig !== null) {
                return $fromConfig;
            }
        }

        return data_get(self::$values, $var, $default);
    }

    /**
     * Read and delete a value from the store.
     *
     * @since 1.0.0
     */
    public static function consume(string $var): mixed
    {
        $value = data_get(self::$values, $var);
        data_set(self::$values, $var, null);

        return $value;
    }

    /**
     * Check if a key is set (and not null) in the store.
     *
     * @since 1.0.0
     */
    public static function check(string $var): bool
    {
        return data_get(self::$values, $var) !== null;
    }

    /**
     * Delete a key from the store.
     *
     * @since 1.0.0
     */
    public static function delete(string $var): void
    {
        // Traverse and unset via dot notation
        $keys = explode('.', $var);
        $last = array_pop($keys);
        $ref  = &self::$values;

        foreach ($keys as $key) {
            if (!isset($ref[$key]) || !is_array($ref[$key])) {
                return;
            }
            $ref = &$ref[$key];
        }

        unset($ref[$last]);
    }

    /**
     * Copy all $_ENV values into Configure under the 'ENV.' prefix.
     *
     * @deprecated ENV values are now available via env() directly.
     * @since 1.0.0
     */
    public static function boot(): void
    {
        foreach ($_ENV as $k => $v) {
            self::write('ENV.' . $k, $v);
        }
    }

    /**
     * Reset all values — used in tests.
     *
     * @since 1.0.0
     */
    public static function reset(): void
    {
        self::$values = [];
    }

    /**
     * Dump all stored values.
     *
     * @since 1.0.0
     */
    public static function debug(): void
    {
        debug(self::$values);
    }
}
