<?php

declare(strict_types=1);

namespace Sloth\Utility;

use Illuminate\Support\Str;

/**
 * Utility class with helper methods for name conversion.
 *
 * This class provides static methods for converting names between different
 * naming conventions used throughout the Sloth framework:
 * - Module naming (PascalCase with Module suffix)
 * - View naming (kebab-case)
 * - ACF naming (snake_case with optional prefix)
 * - Normalized names (cleaned for comparison)
 *
 * @since 1.0.0
 * @see \Illuminate\Support\Str For the underlying string manipulation
 */
class Utility
{
    /**
     * Normalize a name by removing namespace prefix and cleaning formatting.
     *
     * Extracts the short class name from a fully qualified class name
     * and removes trailing "Module" suffix if present.
     *
     * @since 1.0.0
     *
     * @param string $name The name to normalize (e.g., 'App\Controller\PageController' or 'HeaderModule')
     *
     * @return string The normalized name (e.g., 'PageController' or 'Header')
     *
     * @example
     * ```php
     * Utility::normalize('App\Controller\PageController'); // 'PageController'
     * Utility::normalize('Some\Namespace\HeaderModule');    // 'Header'
     * Utility::normalize('My Class Name');                 // 'My-Class-Name'
     * ```
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
     * Convert a name to a module class name format.
     *
     * Transforms a normalized name into a PascalCase module class name
     * with a 'Module' suffix. Used for generating module class names
     * from various input formats.
     *
     * @since 1.0.0
     *
     * @param string $name The name to convert (e.g., 'page', 'home-page', 'HeaderModule')
     * @param bool $namespaced Whether to prefix with 'Theme\Module\' namespace
     *
     * @return string The module class name (e.g., 'PageModule' or 'Theme\Module\PageModule')
     *
     * @example
     * ```php
     * Utility::modulize('page');            // 'PageModule'
     * Utility::modulize('home-page');       // 'HomePageModule'
     * Utility::modulize('hero-section');    // 'HeroSectionModule'
     * Utility::modulize('page', true);      // 'Theme\Module\PageModule'
     * ```
     */
    public static function modulize(string $name, bool $namespaced = false): string
    {
        $name = self::normalize($name);

        $name = ucfirst(Str::camel(str_replace('-', '_', $name))) . 'Module';

        if ($namespaced) {
            return 'Theme\\Module\\' . $name;
        }

        return $name;
    }

    /**
     * Convert a name to a view-friendly kebab-case format.
     *
     * Transforms a name into kebab-case suitable for use as a view
     * filename or template identifier. Commonly used for resolving
     * template paths.
     *
     * @since 1.0.0
     *
     * @param string $name The name to convert (e.g., 'PageController', 'HomePage')
     *
     * @return string The kebab-case name (e.g., 'page-controller', 'home-page')
     *
     * @example
     * ```php
     * Utility::viewize('PageController');    // 'page-controller'
     * Utility::viewize('HomePage');          // 'home-page'
     * Utility::viewize('MyCustomClass');     // 'my-custom-class'
     * Utility::viewize('HeroSection');       // 'hero-section'
     * ```
     */
    public static function viewize(string $name): string
    {
        $name = self::normalize($name);

        return Str::kebab($name);
    }

    /**
     * Convert a name to an ACF field group format.
     *
     * Transforms a name into snake_case suitable for ACF field group
     * keys. Optionally adds the 'group_module_' prefix used by Sloth
     * for module-specific field groups.
     *
     * @since 1.0.0
     *
     * @param string $name The name to convert (e.g., 'hero_image', 'HeroImage')
     * @param bool $prefixed Whether to add the 'group_module_' prefix
     *
     * @return string The ACF-formatted name (e.g., 'group_module_hero_image' or 'hero_image')
     *
     * @example
     * ```php
     * Utility::acfize('hero_image');        // 'group_module_hero_image'
     * Utility::acfize('HeroImage');          // 'group_module_hero_image'
     * Utility::acfize('my_field', false);   // 'my_field'
     * Utility::acfize('featured_posts');     // 'group_module_featured_posts'
     * ```
     */
    public static function acfize(string $name, bool $prefixed = true): string
    {
        $name = self::normalize($name);

        $name = Str::snake($name);

        if ($prefixed) {
            return 'group_module_' . $name;
        }

        return $name;
    }

    /**
     * Convert a floating point number to its closest fractional representation.
     *
     * Uses a continued fraction algorithm to find the best rational
     * approximation of the given float. Useful for aspect ratios,
     * image dimensions, or any context where fractions are preferred.
     *
     * @since 1.0.0
     *
     * @param float $n The floating point number to convert (must be > 0)
     * @param float $tolerance The maximum relative error allowed (default: 1e-6)
     *
     * @return string The fraction in 'numerator/denominator' format
     *
     * @example
     * ```php
     * Utility::float2fraction(0.5);    // '1/2'
     * Utility::float2fraction(0.25);  // '1/4'
     * Utility::float2fraction(0.333); // '1/3'
     * Utility::float2fraction(1.5);   // '3/2'
     * ```
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
