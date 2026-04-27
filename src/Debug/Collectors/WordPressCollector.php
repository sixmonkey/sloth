<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collector for WordPress context: post type, queried object, template, hooks, admin.
 */
class WordPressCollector extends DataCollector implements Renderable
{
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
        try {
            return get_post_type() ?: 'none';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getQueriedObjectId(): int
    {
        try {
            return (int) get_queried_object_id();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getTemplateSlug(): string
    {
        try {
            return get_page_template_slug() ?: 'default';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getHooksCount(): int
    {
        try {
            global $wp_filter;
            return is_array($wp_filter) ? count($wp_filter) : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function isAdmin(): bool
    {
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
