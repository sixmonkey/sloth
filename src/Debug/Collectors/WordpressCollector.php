<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * WordPress Collector.
 *
 * Collects and displays *-specific data including
 * version, and service plugins.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class WordpressCollector extends DataCollector implements Renderable
{
    /**
     * Collect the WordPress data.
     *
     * Gathers information about the WordPress version.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        return [
            'Version' => wp_get_wp_version(),
        ];
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
     * displaying wordpress data.
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
