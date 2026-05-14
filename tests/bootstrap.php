<?php

declare(strict_types=1);

/**
 * Testing Bootstrap File
 *
 * This file sets up the testing environment with WordPress function stubs.
 *
 * @since 1.1.0
 */

use Brain\Monkey;

// Define constants required for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('DIR_CACHE')) {
    define('DIR_CACHE', __DIR__ . '/cache');
}

if (!defined('DIR_APP')) {
    define('DIR_APP', __DIR__ . '/../app');
}

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

if (!defined('DIR_ROOT')) {
    define('DIR_ROOT', dirname(__DIR__));
}

if (!defined('WP_HOME')) {
    define('WP_HOME', 'http://localhost');
}

if (!defined('WP_ENV')) {
    define('WP_ENV', 'development');
}

if (!defined('WP_TESTS_PHASE')) {
    define('WP_TESTS_PHASE', true);
}

// Create cache directory for tests
$cacheDir = __DIR__ . '/cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0o755, true);
}

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Stub WordPress functions
if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        return $value;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $tag, callable $function_to_add, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('remove_filter')) {
    function remove_filter(string $tag, callable $function_to_remove, int $priority = 10): bool
    {
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $tag, mixed ...$args): void {}
}

if (!function_exists('add_action')) {
    function add_action(string $tag, callable $function_to_add, int $priority = 10, int $accepted_args = 1): bool
    {
        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $tag, callable $function_to_remove, int $priority = 10): bool
    {
        return true;
    }
}

if (!function_exists('add_rewrite_tag')) {
    function add_rewrite_tag(string $tag, string $regex): bool
    {
        return true;
    }
}

if (!function_exists('add_rewrite_rule')) {
    function add_rewrite_rule(string $regex, string $redirect, string $match = 'top'): bool
    {
        return true;
    }
}

if (!function_exists('wp_upload_dir')) {
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

if (!function_exists('get_template_directory')) {
    function get_template_directory(): string
    {
        return __DIR__ . '/../src';
    }
}

if (!function_exists('get_template_directory_uri')) {
    function get_template_directory_uri(): string
    {
        return 'http://localhost/wp-content/themes/test';
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string
    {
        return __DIR__ . '/../src';
    }
}

if (!function_exists('content_url')) {
    function content_url(?string $path = ''): string
    {
        $url = 'http://localhost/wp-content';
        return $path ? $url . '/' . ltrim($path, '/') : $url;
    }
}

if (!function_exists('wp_dequeue_style')) {
    function wp_dequeue_style(string $handle): void {}
}

if (!function_exists('add_image_size')) {
    function add_image_size(string $name, int $width = 0, int $height = 0, bool $crop = false): void {}
}

if (!function_exists('register_nav_menu')) {
    function register_nav_menu(string $location, string $description): void {}
}

if (!function_exists('is_blog_installed')) {
    function is_blog_installed(): bool
    {
        return true;
    }
}

if (!function_exists('wp_get_wp_version')) {
    function wp_get_wp_version(): string
    {
        return '6.0.0';
    }
}

if (!function_exists('get_post_type_object')) {
    function get_post_type_object(string $post_type): ?object
    {
        return null;
    }
}

if (!function_exists('home_url')) {
    function home_url(?string $path = ''): string
    {
        return 'http://localhost/' . $path;
    }
}

if (!function_exists('__return_true')) {
    function __return_true(): bool
    {
        return true;
    }
}

if (!function_exists('__return_false')) {
    function __return_false(): bool
    {
        return false;
    }
}

require_once __DIR__ . '/WP_CLI_stub.php';
