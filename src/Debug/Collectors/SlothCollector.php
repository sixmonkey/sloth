<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use Brain\Hierarchy\Hierarchy;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;

/**
 * Collector for Sloth-specific data: container state, models, taxonomies.
 */
class SlothCollector extends DataCollector implements Renderable
{
    public function __construct(private $app)
    {
    }

    public function collect(): array
    {
        return [
            'Environment' => $this->getEnvironment(),
            'Template-Hierarchy' => $this->getTemplateHierarchy(),
            'Models' => $this->getModels(),
            'Taxonomies' => $this->getTaxonomies(),
            'Loaded providers' => $this->getProviders(),
        ];
    }

    private function getProviders(): string
    {
        try {
            return $this->app->getLoadedProviders()->keys()->join("\n");
        } catch (\Throwable) {
            return 'None';
        }
    }

    private function getEnvironment(): string
    {
        try {
            return $this->app->environment();
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getModels(): string
    {
        try {
            return collect(app('sloth.models'))
                ->map(function ($class, $name) {
                    return Str::ucfirst($name) . " => " . $class;
                })
                ->join("\n");
        } catch (\Throwable) {
            return 'None';
        }
    }

    private function getTaxonomies(): string
    {
        try {
            return collect(app('sloth.taxonomies'))
                ->map(function ($class, $name) {
                    return Str::ucfirst($name) . " => " . $class;
                })
                ->join("\n");
        } catch (\Throwable) {
            return 'None';
        }
    }

    private function getTemplateHierarchy(): string
    {
        return ''; #app('sloth.current_template');
        /*$hierarchy = new Hierarchy();
        return collect($hierarchy->templates())
            ->map(function ($template) {
                if (app('sloth.current_layout') == $template) {
                    $template .= ' <-';
                }
                return $template;
            })
            ->join("\n");*/
    }

    public function getName(): string
    {
        return 'sloth';
    }

    public function getWidgets(): array
    {
        return [
            'sloth' => [
                'map' => 'sloth',
                'widget' => 'PhpDebugBar.Widgets.KVListWidget',
                'default' => '{}',
            ],
        ];
    }
}
