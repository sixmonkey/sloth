<?php

declare(strict_types=1);

namespace Sloth\Utility;

use Cake\Utility\Inflector;

/**
 * Utility class extending CakePHP Inflector with additional helper methods.
 *
 * @since 1.0.0
 * @extends Inflector
 */
class Utility extends Inflector
{
    /**
     * Normalize a name by removing namespace and cleaning formatting.
     *
     * @since 1.0.0
     *
     * @param string $name The name to normalize
     *
     * @return string The normalized name
     */
    public static function normalize(string $name): string
    {
        if (strstr($name, '\\')) {
            $name = substr(strrchr($name, '\\'), 1);
        }

        $name = preg_replace('/Module$/', '', $name);

        return str_replace(' ', '-', $name);
    }

    /**
     * Convert a name to a module name format.
     *
     * @since 1.0.0
     *
     * @param string $name       The name to convert
     * @param bool   $namespaced Whether to include the Theme namespace
     *
     * @return string The modulized name
     */
    public static function modulize(string $name, bool $namespaced = false): string
    {
        $name = self::normalize($name);

        $name = self::camelize(str_replace('-', '_', $name)) . 'Module';

        if ($namespaced) {
            return 'Theme\\Module\\' . $name;
        }

        return $name;
    }

    /**
     * Convert a name to a view-friendly format (dasherized).
     *
     * @since 1.0.0
     *
     * @param string $name The name to convert
     *
     * @return string The view-friendly name
     */
    public static function viewize(string $name): string
    {
        $name = self::normalize($name);

        return self::dasherize($name);
    }

    /**
     * Convert a name to an ACF field group format.
     *
     * @since 1.0.0
     *
     * @param string $name     The name to convert
     * @param bool   $prefixed Whether to add the group_module_ prefix
     *
     * @return string The ACF-formatted name
     */
    public static function acfize(string $name, bool $prefixed = true): string
    {
        $name = self::normalize($name);

        $name = self::underscore($name);

        if ($prefixed) {
            return 'group_module_' . $name;
        }

        return $name;
    }

    /**
     * Convert a float to a fraction string.
     *
     * @since 1.0.0
     *
     * @param float $n          The number to convert
     * @param float $tolerance  The tolerance for conversion
     *
     * @return string The fraction string
     */
    public static function float2fraction(float $n, float $tolerance = 1.e-6): string
    {
        $h1 = 1;
        $h2 = 0;
        $k1 = 0;
        $k2 = 1;
        $b = 1 / $n;

        do {
            $b = 1 / $b;
            $a = floor($b);
            $aux = $h1;
            $h1 = $a * $h1 + $h2;
            $h2 = $aux;
            $aux = $k1;
            $k1 = $a * $k1 + $k2;
            $k2 = $aux;
            $b -= $a;
        } while (abs($n - $h1 / $k1) > $n * $tolerance);

        return sprintf('%s/%s', $h1, $k1);
    }
}
