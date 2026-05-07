<?php

declare(strict_types=1);
namespace Sloth\Debug\Collectors;

use Brain\Hierarchy\Hierarchy;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Illuminate\Support\Str;
use Throwable;

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
     * @return array<string, mixed> the collected data
     *
     * @since 1.0.0
     */
    public function collect(): array
    {
        return [
            'Environment'        => $this->getEnvironment(),
            'Template-Hierarchy' => $this->getTemplateHierarchy(),
            'Models'             => $this->getModels(),
            'Taxonomies'         => $this->getTaxonomies(),
            'Loaded providers'   => $this->getProviders(),
        ];
    }

    /**
     * Get the list of loaded service providers.
     *
     * @return string the provider names joined by newlines
     *
     * @since 1.0.0
     */
    private function getProviders(): string
    {
        try {
            return app()->getLoadedProviders()->keys()->join("\n");
        } catch (Throwable) {
            return 'None';
        }
    }

    /**
     * Get the current application environment.
     *
     * @return string the environment name
     *
     * @since 1.0.0
     */
    private function getEnvironment(): string
    {
        try {
            return app()->environment();
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /**
     * Get the registered Sloth models.
     *
     * @return string the model names and classes joined by newlines
     *
     * @since 1.0.0
     */
    private function getModels(): string
    {
        try {
            return collect(app('sloth.models'))
                ->map(function ($class, $name) {
                    return Str::ucfirst($name) . ' => ' . $class;
                })
                ->join("\n")
            ;
        } catch (Throwable) {
            return 'None';
        }
    }

    /**
     * Get the registered Sloth taxonomies.
     *
     * @return string the taxonomy names and classes joined by newlines
     *
     * @since 1.0.0
     */
    private function getTaxonomies(): string
    {
        try {
            return collect(app('sloth.taxonomies'))
                ->map(function ($class, $name) {
                    return Str::ucfirst($name) . ' => ' . $class;
                })
                ->join("\n")
            ;
        } catch (Throwable) {
            return 'None';
        }
    }

    /**
     * Get the WordPress template hierarchy.
     *
     * Returns the list of templates that would be used
     * for the current request, with the active layout marked.
     *
     * @return string the template hierarchy as a newline-separated string
     *
     * @since 1.0.0
     */
    private function getTemplateHierarchy(): string
    {
        try {
            $hierarchy = new Hierarchy();
            $templates = $hierarchy->templates();

            if (empty($templates)) {
                return 'None';
            }

            $current = app('sloth.current_layout') ?? '';

            return collect($templates)
                ->map(function ($template) use ($current) {
                    if ($template === $current) {
                        return $template . ' (active)';
                    }

                    return $template;
                })
                ->join("\n")
            ;
        } catch (Throwable) {
            return 'None';
        }
    }

    /**
     * Get the collector name.
     *
     * @return string the collector identifier
     *
     * @since 1.0.0
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
     * @return array<string, mixed> the widget configuration
     *
     * @since 1.0.0
     */
    public function getWidgets(): array
    {
        return [
            'sloth' => [
                'icon'    => 'brand-folivoro',
                'map'     => 'sloth',
                'widget'  => 'PhpDebugBar.Widgets.KVListWidget',
                'default' => '{}',
            ],
        ];
    }
}
