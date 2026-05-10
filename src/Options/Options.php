<?php

declare(strict_types=1);
namespace Sloth\Options;

/**
 * Options accessor for WordPress Core and ACF Options.
 *
 * Provides a unified API over get_option() and ACF's get_field()
 * for option pages. WordPress' own object cache handles caching —
 * no additional layer needed.
 *
 * ## Resolution order
 *
 * 1. ACF option field — if ACF is active and field exists
 * 2. WordPress core option — get_option()
 * 3. Default value
 *
 * ## Usage
 *
 * ```php
 * use Sloth\Facades\Options;
 *
 * Options::get('blogname')
 * Options::get('primary_color')           // ACF option field
 * Options::get('my_option', 'fallback')
 * Options::set('my_option', 'value')
 * Options::has('my_option')
 * Options::delete('my_option')
 * ```
 *
 * ## Magic property access
 *
 * ```php
 * app('options')->blogname
 * app('options')->primary_color
 * ```
 *
 * ## In Twig
 *
 * ```twig
 * {{ options.blogname }}
 * {{ options.primary_color }}
 * ```
 *
 * @since 1.0.0
 */
class Options
{
    /**
     * Get an option value.
     *
     * Tries ACF option field first when ACF is available,
     * falls back to WordPress core get_option().
     *
     * @param string $key     option key
     * @param mixed  $default default value if option is not found
     *
     * @since 1.0.0
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (function_exists('get_field')) {
            $acfValue = get_field($key, 'option');

            if ($acfValue !== null && $acfValue !== false) {
                return $acfValue;
            }
        }

        $value = get_option($key, $default);

        return $value !== false ? $value : $default;
    }

    /**
     * Set an option value.
     *
     * Always uses WordPress core update_option() —
     * ACF options are managed via ACF's own save mechanism.
     *
     * @param  string $key   option key
     * @param  mixed  $value option value
     * @return bool   true on success, false on failure
     *
     * @since 1.0.0
     */
    public function set(string $key, mixed $value): bool
    {
        return update_option($key, $value);
    }

    /**
     * Check whether an option exists and is not empty.
     *
     * @param string $key option key
     *
     * @since 1.0.0
     */
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Delete an option.
     *
     * @param  string $key option key
     * @return bool   true on success, false on failure
     *
     * @since 1.0.0
     */
    public function delete(string $key): bool
    {
        return delete_option($key);
    }

    /**
     * Magic property access — allows $options->blogname.
     *
     * @param string $key option key
     *
     * @since 1.0.0
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Magic isset — allows isset($options->blogname).
     *
     * @param string $key option key
     *
     * @since 1.0.0
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }
}
