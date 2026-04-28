<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;

/**
 * Sloth Framework Collector.
 *
 * Collects and displays Sloth-specific data including
 * environment, models, taxonomies, and service providers.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class SlothCollector extends DataCollector implements Renderable
{
    /**
     * Collect the Sloth framework data.
     *
     * Gathers information about the Sloth environment,
     * template hierarchy, registered models, taxonomies,
     * and loaded service providers.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
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

    /**
     * Get the list of loaded service providers.
     *
     * @since 1.0.0
     * @return string The provider names joined by newlines.
     */
    private function getProviders(): string
    {
        try {
            return app()->getLoadedProviders()->keys()->join("\n");
        } catch (\Throwable) {
            return 'None';
        }
    }

    /**
     * Get the current application environment.
     *
     * @since 1.0.0
     * @return string The environment name.
     */
    private function getEnvironment(): string
    {
        try {
            return app()->environment();
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the registered Sloth models.
     *
     * @since 1.0.0
     * @return string The model names and classes joined by newlines.
     */
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

    /**
     * Get the registered Sloth taxonomies.
     *
     * @since 1.0.0
     * @return string The taxonomy names and classes joined by newlines.
     */
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

    /**
     * Get the WordPress template hierarchy.
     *
     * Returns the list of templates that would be used
     * for the current request, with the active layout marked.
     *
     * @since 1.0.0
     * @return string The template hierarchy as a newline-separated string.
     */
    private function getTemplateHierarchy(): string
    {
        return ''; // TODO: Re-enable when Hierarchy package is available.
        // $hierarchy = new Hierarchy();
        // return collect($hierarchy->templates())
        //     ->map(function ($template) {
        //         if (app('sloth.current_layout') == $template) {
        //             $template .= ' <-';
        //         }
        //         return $template;
        //     })
        //     ->join("\n");
    }

    /**
     * Get the collector name.
     *
     * @since 1.0.0
     * @return string The collector identifier.
     */
    public function getName(): string
    {
        return 'sloth';
    }

    /**
     * Get the widgets for this collector.
     *
     * Returns the debug bar widget configuration for
     * displaying Sloth framework data.
     *
     * @since 1.0.0
     * @return array<string, mixed> The widget configuration.
     */
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
