<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use Brain\Hierarchy\Hierarchy;
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
     * Create a new SlothCollector instance.
     *
     * @since 1.0.0
     * @param \Illuminate\Contracts\Container\ContainerInterface $app The application container.
     */
    public function __construct(private $app)
    {
    }

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

    private function getProviders(): string
    {
        /**
         * Get the list of loaded service providers.
         *
         * @since 1.0.0
         * @return string The provider names joined by newlines.
         */
        try {
            return $this->app->getLoadedProviders()->keys()->join("\n");
        } catch (\Throwable) {
            return 'None';
        }
    }

    private function getEnvironment(): string
    {
        /**
         * Get the current application environment.
         *
         * @since 1.0.0
         * @return string The environment name.
         */
        try {
            return $this->app->environment();
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    private function getModels(): string
    {
        /**
         * Get the registered Sloth models.
         *
         * @since 1.0.0
         * @return string The model names and classes joined by newlines.
         */
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
        /**
         * Get the registered Sloth taxonomies.
         *
         * @since 1.0.0
         * @return string The taxonomy names and classes joined by newlines.
         */
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
        /**
         * Get the WordPress template hierarchy.
         *
         * Returns the list of templates that would be used
         * for the current request, with the active layout marked.
         *
         * @since 1.0.0
         * @return string The template hierarchy as a newline-separated string.
         */
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
