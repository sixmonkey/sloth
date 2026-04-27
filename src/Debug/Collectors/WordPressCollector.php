<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * WordPress Context Collector.
 *
 * Collects and displays WordPress-specific data including
 * post type, queried object, template, hooks count, and admin mode.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class WordPressCollector extends DataCollector implements Renderable
{
    /**
     * Collect the WordPress context data.
     *
     * Gathers information about the current WordPress
     * request including post type, queried object, template,
     * registered hooks count, and whether in admin area.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        return [
            'post_type' => $this->getPostType(),
            'object_id' => $this->getQueriedObjectId(),
            'template' => $this->getTemplateSlug(),
            'hooks_count' => $this->getHooksCount(),
            'is_admin' => $this->isAdmin(),
        ];
    }

    private function getPostType(): string
    {
        /**
         * Get the current post type.
         *
         * @since 1.0.0
         * @return string The post type name or 'none' if not set.
         */
        try {
            return get_post_type() ?: 'none';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getQueriedObjectId(): int
    {
        /**
         * Get the ID of the queried object.
         *
         * @since 1.0.0
         * @return int The queried object ID.
         */
        try {
            return (int) get_queried_object_id();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getTemplateSlug(): string
    {
        /**
         * Get the template slug for the current page.
         *
         * @since 1.0.0
         * @return string The template slug or 'default' if none set.
         */
        try {
            return get_page_template_slug() ?: 'default';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getHooksCount(): int
    {
        /**
         * Get the count of registered WordPress hooks.
         *
         * @since 1.0.0
         * @return int The number of registered hooks.
         */
        try {
            global $wp_filter;
            return is_array($wp_filter) ? count($wp_filter) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function isAdmin(): bool
    {
        /**
         * Check if the current request is in the admin area.
         *
         * @since 1.0.0
         * @return bool True if in admin area, false otherwise.
         */
        try {
            return (bool) is_admin();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getName(): string
    {
        return 'wordpress';
    }

    public function getWidgets(): array
    {
        return [
            'wordpress' => [
                'icon' => '🔰',
                'widget' => 'PhpDebugBar.Widget',
                'map' => 'wordpress',
                'attrs' => [
                    'title' => 'WordPress',
                ],
            ],
        ];
    }
}
