<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * WordPress Collector.
 *
 * Collects and displays WordPress-specific data including
 * version, active theme, plugins, current post, user,
 * conditional tags, constants, and matched query.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class WordpressCollector extends DataCollector implements Renderable
{
    /**
     * Collect the WordPress data.
     *
     * Gathers information about the WordPress installation,
     * current request state, and environment.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        return [
            'Version' => $this->getVersion(),
            'Active Theme' => $this->getTheme(),
            'Active Plugins' => $this->getPlugins(),
            'Current Post' => $this->getCurrentPost(),
            'Current User' => $this->getCurrentUser(),
            'Conditional Tags' => $this->getConditionals(),
            'WP Constants' => $this->getWpConstants(),
            'Matched Query' => $this->getMatchedQuery(),
        ];
    }

    /**
     * Get the WordPress version string.
     *
     * @return string The WP version.
     */
    private function getVersion(): string
    {
        try {
            return wp_get_wp_version();
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the active theme name and version.
     *
     * @return string Theme name and version.
     */
    private function getTheme(): string
    {
        try {
            $theme = wp_get_theme();
            $version = $theme->get('Version') ?? '';
            return $version ? "{$theme->name} (v{$version})" : $theme->name;
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the list of active plugins with versions.
     *
     * @return string Newline-separated plugin list.
     */
    private function getPlugins(): string
    {
        try {
            $parts = [];

            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $allPlugins = function_exists('get_plugins') ? get_plugins() : [];

            $active = get_option('active_plugins', []);
            foreach ($active as $pluginFile) {
                if (isset($allPlugins[$pluginFile])) {
                    $info = $allPlugins[$pluginFile];
                    $name = $info['Name'] ?? basename($pluginFile);
                    $version = $info['Version'] ?? '';
                    $parts[] = $version ? "{$name} (v{$version})" : $name;
                } else {
                    $parts[] = basename($pluginFile);
                }
            }

            return $parts ? implode("\n", $parts) : 'None';
        } catch (\Throwable) {
            return 'None';
        }
    }

    /**
     * Get the current post information.
     *
     * @return string Post ID, title, and type.
     */
    private function getCurrentPost(): string
    {
        try {
            $post = get_post();
            if (!$post) {
                return 'None';
            }
            return "Post #{$post->ID} — {$post->post_title} ({$post->post_type})";
        } catch (\Throwable) {
            return 'None';
        }
    }

    /**
     * Get the current user information.
     *
     * @return string Username and role(s).
     */
    private function getCurrentUser(): string
    {
        try {
            $user = wp_get_current_user();
            if (!$user || !$user->exists()) {
                return '(not logged in)';
            }
            $roles = implode(', ', $user->roles) ?: '(no role)';
            return "{$user->display_name} ({$roles})";
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the values of common WordPress conditional tags.
     *
     * @return string Newline-separated conditional tag values.
     */
    private function getConditionals(): string
    {
        try {
            $conditionals = [
                'is_home',
                'is_front_page',
                'is_single',
                'is_page',
                'is_archive',
                'is_search',
                'is_404',
                'is_admin',
                'is_singular',
                'is_attachment',
                'is_category',
                'is_tag',
                'is_author',
                'is_tax',
                'is_user_logged_in',
            ];

            $parts = [];
            foreach ($conditionals as $fn) {
                $value = function_exists($fn) ? ($fn() ? 'true' : 'false') : 'N/A';
                $parts[] = "{$fn}: {$value}";
            }

            return implode("\n", $parts);
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the values of relevant WordPress debug constants.
     *
     * @return string Newline-separated constant values.
     */
    private function getWpConstants(): string
    {
        try {
            $constants = [
                'WP_DEBUG',
                'WP_DEBUG_LOG',
                'WP_DEBUG_DISPLAY',
                'SCRIPT_DEBUG',
                'SAVEQUERIES',
                'WP_CACHE',
                'WP_MEMORY_LIMIT',
                'WP_MAX_MEMORY_LIMIT',
                'WP_CONTENT_DIR',
            ];

            $parts = [];
            foreach ($constants as $name) {
                $value = defined($name)
                    ? (is_bool(constant($name)) ? (constant($name) ? 'true' : 'false') : constant($name))
                    : 'not defined';
                $parts[] = "{$name}: {$value}";
            }

            return implode("\n", $parts);
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the matched WP-Query variables for the current request.
     *
     * @return string Newline-separated query variables.
     */
    private function getMatchedQuery(): string
    {
        try {
            global $wp;
            $parts = [];

            if (isset($wp->query_vars) && is_array($wp->query_vars)) {
                foreach ($wp->query_vars as $key => $value) {
                    $parts[] = "{$key}: " . (is_array($value) ? json_encode($value) : $value);
                }
            }

            return $parts ? implode("\n", $parts) : 'None';
        } catch (\Throwable) {
            return 'None';
        }
    }

    /**
     * Get the collector name.
     *
     * @since 1.0.0
     * @return string The collector identifier.
     */
    public function getName(): string
    {
        return 'wordpress';
    }

    /**
     * Get the widgets for this collector.
     *
     * Returns the debug bar widget configuration for
     * displaying WordPress data.
     *
     * @since 1.0.0
     * @return array<string, mixed> The widget configuration.
     */
    public function getWidgets(): array
    {
        return [
            'wordpress' => [
                'icon' => 'brand-wordpress',
                'map' => 'wordpress',
                'widget' => 'PhpDebugBar.Widgets.KVListWidget',
                'default' => '{}',
            ],
        ];
    }
}
