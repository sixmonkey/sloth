<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use Brain\Hierarchy\Hierarchy;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Template Hierarchy Collector.
 *
 * Collects and displays the WordPress template hierarchy
 * including the current layout and candidate templates.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class TemplateCollector extends DataCollector implements Renderable
{
    /**
     * Create a new TemplateCollector instance.
     *
     * @since 1.0.0
     * @param \Illuminate\Contracts\Container\ContainerInterface $app The application container.
     */
    public function __construct(private $app)
    {
    }

    /**
     * Collect the template hierarchy data.
     *
     * Gathers information about the current layout
     * and all candidate templates from the hierarchy.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        $h = new Hierarchy();

        try {
            $currentLayout = $this->app['sloth.current_layout'] ?? 'none';
        } catch (\Throwable) {
            $currentLayout = 'none';
        }

        return [
            'current' => $currentLayout,
            'templates' => $h->templates(),
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
        return 'template';
    }

    /**
     * Get the widgets for this collector.
     *
     * Returns the debug bar widget configuration for
     * displaying template hierarchy information.
     *
     * @since 1.0.0
     * @return array<string, mixed> The widget configuration.
     */
    public function getWidgets(): array
    {
        return [
            'template' => [
                'icon' => '📄',
                'widget' => 'PhpDebugBar.Widget',
                'map' => 'template',
                'attrs' => [
                    'title' => 'Templates',
                ],
            ],
        ];
    }
}