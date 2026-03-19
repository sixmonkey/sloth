<?php

/**
 * PHPUnit Bootstrap File
 *
 * This file is loaded before running PHPUnit tests.
 * It sets up the testing environment and any necessary stubs.
 *
 * @since 1.1.0
 */

// Define constants required for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// Autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// Mock WordPress functions that may be called
if (!function_exists('apply_filters')) {
    /**
     * Mock apply_filters for testing.
     *
     * @param string $tag The filter hook name.
     * @param mixed  $value The value to filter.
     * @param mixed  ...$args Additional arguments passed to the filter.
     * @return mixed The filtered value.
     */
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (!function_exists('add_filter')) {
    /**
     * Mock add_filter for testing.
     *
     * @param string   $tag             The name of the filter hook.
     * @param callable $function_to_add The callback function.
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  are executed. Default 10.
     * @param int      $accepted_args   Optional. The number of arguments the function accepts.
     *                                  Default 1.
     * @return true Always returns true.
     */
    function add_filter(string $tag, callable $function_to_add, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('do_action')) {
    /**
     * Mock do_action for testing.
     *
     * @param string $tag The name of the action hook.
     * @param mixed  ...$args Arguments passed to the action.
     */
    function do_action(string $tag, mixed ...$args): void
    {
    }
}

if (!function_exists('add_action')) {
    /**
     * Mock add_action for testing.
     *
     * @param string   $tag             The name of the action hook.
     * @param callable $function_to_add The callback function.
     * @param int      $priority        Optional. Used to specify the order in which the functions
     *                                  are executed. Default 10.
     * @param int      $accepted_args   Optional. The number of arguments the function accepts.
     *                                  Default 1.
     * @return true Always returns true.
     */
    function add_action(string $tag, callable $function_to_add, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('add_rewrite_tag')) {
    /**
     * Mock add_rewrite_tag for testing.
     *
     * @param string $tag   The name of the query var to add.
     * @param string $regex The regex pattern to match.
     * @return true Always returns true.
     */
    function add_rewrite_tag(string $tag, string $regex): bool
    {
        return true;
    }
}

if (!function_exists('add_rewrite_rule')) {
    /**
     * Mock add_rewrite_rule for testing.
     *
     * @param string $regex       The regex pattern to match.
     * @param string $redirect     The redirect destination.
     * @param string $match        Optional. The type of redirect. Default 'top'.
     * @return true Always returns true.
     */
    function add_rewrite_rule(string $regex, string $redirect, string $match = 'top'): bool
    {
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
    /**
     * Mock wp_upload_dir for testing.
     *
     * @param string|null $time   Optional. Time formatted as 'yyyy/mm'. Default null.
     * @param bool        $create Whether to create the uploads directory. Default false.
     * @param bool        $refresh Whether to refresh the cache. Default false.
     * @return array<string, string|int> Upload directory information.
     */
    function wp_upload_dir(?string $time = null, bool $create = false, bool $refresh = false): array
    {
        return [
            'path'    => WP_CONTENT_DIR . '/uploads',
            'url'     => 'http://example.com/wp-content/uploads',
            'subdir'  => '',
            'basedir' => WP_CONTENT_DIR . '/uploads',
            'baseurl' => 'http://example.com/wp-content/uploads',
            'error'   => false,
        ];
    }
}

if (!function_exists('get_option')) {
    /**
     * Mock get_option for testing.
     *
     * @param string $option  Name of the option to retrieve.
     * @param mixed  $default Optional. Default value to return if option doesn't exist.
     * @return mixed The option value.
     */
    function get_option(string $option, mixed $default = false): mixed
    {
        return $default;
    }
}

if (!function_exists('update_option')) {
    /**
     * Mock update_option for testing.
     *
     * @param string $option   Name of the option to update.
     * @param mixed  $value     The new value for the option.
     * @param bool   $autoload Optional. Whether to load the option on startup. Default true.
     * @return bool True if option value changed, false otherwise.
     */
    function update_option(string $option, mixed $value, bool $autoload = true): bool
    {
        return true;
    }
}

if (!function_exists('get_template_directory')) {
    /**
     * Mock get_template_directory for testing.
     *
     * @return string The template directory path.
     */
    function get_template_directory(): string
    {
        return __DIR__ . '/../src';
    }
}

if (!function_exists('get_stylesheet_directory')) {
    /**
     * Mock get_stylesheet_directory for testing.
     *
     * @return string The stylesheet directory path.
     */
    function get_stylesheet_directory(): string
    {
        return __DIR__ . '/../src';
    }
}

if (!defined('DIR_CACHE')) {
    define('DIR_CACHE', __DIR__ . '/cache');
}

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

// Create cache directory for tests
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}
