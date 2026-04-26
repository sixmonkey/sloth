<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use Brain\Hierarchy\Hierarchy;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collector for template hierarchy: current layout and candidate templates.
 */
class TemplateCollector extends DataCollector implements Renderable
{
    public function __construct(private $app)
    {
    }

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

    public function getName(): string
    {
        return 'template';
    }

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