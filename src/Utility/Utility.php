<?php

declare(strict_types=1);

namespace Sloth\Utility;

use Illuminate\Support\Str;

/**
 * Utility class with helper methods for name conversion.
 * Uses Laravel's Str class under the hood.
 */
class Utility
{
    public static function normalize(string $name): string
    {
        if (strstr($name, '\\')) {
            $name = substr(strrchr($name, '\\'), 1);
        }

        $name = preg_replace('/Module$/', '', $name);

        return str_replace(' ', '-', $name);
    }

    public static function modulize(string $name, bool $namespaced = false): string
    {
        $name = self::normalize($name);

        $name = ucfirst(Str::camel(str_replace('-', '_', $name))) . 'Module';

        if ($namespaced) {
            return 'Theme\\Module\\' . $name;
        }

        return $name;
    }

    public static function viewize(string $name): string
    {
        $name = self::normalize($name);

        return Str::kebab($name);
    }

    public static function acfize(string $name, bool $prefixed = true): string
    {
        $name = self::normalize($name);

        $name = Str::snake($name);

        if ($prefixed) {
            return 'group_module_' . $name;
        }

        return $name;
    }

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
