<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

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
            'providers' => $this->getProviders(),
            'bindings' => $this->getBindings(),
            'environment' => $this->getEnvironment(),
            'models' => $this->getModels(),
            'taxonomies' => $this->getTaxonomies(),
        ];
    }

    private function getProviders(): int
    {
        try {
            return count($this->app->getLoadedProviders());
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getBindings(): int
    {
        try {
            return count($this->app->getBindings());
        } catch (\Throwable) {
            return 0;
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

    private function getModels(): array
    {
        try {
            return array_keys($this->app['sloth.models'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    private function getTaxonomies(): array
    {
        try {
            return array_keys($this->app['sloth.taxonomies'] ?? []);
        } catch (\Throwable) {
            return [];
        }
    }

    public function getName(): string
    {
        return 'sloth';
    }

    public function getWidgets(): array
    {
        return [
            'sloth' => [
                'icon' => '🦥',
                'widget' => 'PhpDebugBar.Widget',
                'map' => 'sloth',
                'attrs' => [
                    'title' => 'Sloth',
                ],
            ],
        ];
    }

    public function getVarDumperSetup(): void
    {
    }
}