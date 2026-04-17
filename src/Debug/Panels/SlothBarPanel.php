<?php

declare(strict_types=1);

namespace Sloth\Debug\Panels;

use Brain\Hierarchy\Hierarchy;
use Sloth\Facades\View;
use Tracy\IBarPanel;

/**
 * Tracy Debugger Bar Panel for Sloth.
 *
 * Displays comprehensive debug information in the Tracy Bar:
 * - Template hierarchy and current layout (Brain\Hierarchy)
 * - Performance metrics (memory, time)
 * - Container state (loaded providers, bindings)
 * - WordPress context (post type, queried object, hooks)
 * - ACF field groups active on the current page
 * - Registered Sloth models and taxonomies
 *
 * @since 1.0.0
 * @implements IBarPanel
 */
class SlothBarPanel implements IBarPanel
{
    /**
     * Render the panel content shown when the tab is clicked.
     *
     * All data collection is wrapped in try/catch so that a failure
     * in one section never breaks the entire panel or the page.
     *
     * @since 1.0.0
     *
     * @return string HTML for the panel content.
     */
    #[\Override]
    public function getPanel(): string
    {
        try {
            $currentLayout = app('sloth.current_layout') ?? 'none';
        } catch (\Throwable) {
            $currentLayout = 'none';
        }

        return View::make('Debugger.sloth-bar-panel')->with([
            'currentTemplate' => $currentLayout,
            'templates'       => $this->getTemplateHierarchy(),
            'performance'     => $this->getPerformanceData(),
            'container'       => $this->getContainerData(),
            'wordpress'       => $this->getWordPressData(),
            'acf'             => $this->getAcfData(),
            'sloth'           => $this->getSlothData(),
        ])->render();
    }

    /**
     * Render the tab label shown in the Tracy Bar.
     *
     * Uses the Sloth logo SVG if available, otherwise falls back
     * to a plain text label.
     *
     * @since 1.0.0
     *
     * @return string HTML for the tab.
     */
    #[\Override]
    public function getTab(): string
    {
        $logoPath = dirname(__DIR__) . '/logo.svg';

        if (file_exists($logoPath)) {
            $logo = file_get_contents($logoPath);
            return '<span title="SLOTH">' . $logo . '</span>';
        }

        return '<span title="SLOTH">🦥</span>';
    }

    /**
     * Get the Brain\Hierarchy template resolution chain.
     *
     * Shows which templates WordPress would look for in order,
     * helping developers understand template precedence.
     *
     * @since 1.0.0
     *
     * @return array<string> Ordered list of template candidates.
     */
    private function getTemplateHierarchy(): array
    {
        try {
            return (new Hierarchy())->templates();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Collect performance metrics for the current request.
     *
     * @since 1.0.0
     *
     * @return array{memory: string, peak_memory: string, time: string}
     */
    private function getPerformanceData(): array
    {
        return [
            'memory'      => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'time'        => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000) . ' ms',
        ];
    }

    /**
     * Collect Sloth container state information.
     *
     * @since 1.0.0
     *
     * @return array{providers: int, bindings: int, environment: string}
     */
    private function getContainerData(): array
    {
        try {
            return [
                'providers'   => count(app()->getLoadedProviders()),
                'bindings'    => count(app()->getBindings()),
                'environment' => app()->environment(),
            ];
        } catch (\Throwable) {
            return ['providers' => 0, 'bindings' => 0, 'environment' => 'unknown'];
        }
    }

    /**
     * Collect WordPress context for the current request.
     *
     * @since 1.0.0
     *
     * @return array{post_type: string, queried_object_id: int, template_slug: string, hooks: int}
     */
    private function getWordPressData(): array
    {
        try {
            global $wp_filter;

            return [
                'post_type'         => get_post_type() ?: 'none',
                'queried_object_id' => get_queried_object_id(),
                'template_slug'     => get_page_template_slug() ?: 'default',
                'hooks'             => is_array($wp_filter) ? count($wp_filter) : 0,
                'is_admin'          => is_admin() ? 'yes' : 'no',
            ];
        } catch (\Throwable) {
            return [
                'post_type'         => 'unknown',
                'queried_object_id' => 0,
                'template_slug'     => 'unknown',
                'hooks'             => 0,
                'is_admin'          => 'unknown',
            ];
        }
    }

    /**
     * Collect ACF field groups active on the current page.
     *
     * Returns an empty array if ACF is not installed or no groups
     * are registered for the current post.
     *
     * @since 1.0.0
     *
     * @return array<string> List of active ACF field group titles.
     */
    private function getAcfData(): array
    {
        try {
            if (!function_exists('acf_get_field_groups')) {
                return [];
            }

            $groups = acf_get_field_groups(['post_id' => get_the_ID()]);

            return collect($groups)->pluck('title')->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Collect Sloth-specific debug data.
     *
     * Shows which Models and Taxonomies are registered in the container,
     * giving developers a quick overview of the current theme's data model.
     *
     * @since 1.0.0
     *
     * @return array{models: array<string>, taxonomies: array<string>}
     */
    private function getSlothData(): array
    {
        try {
            return [
                'models'     => array_keys(app('sloth.models') ?? []),
                'taxonomies' => array_keys(app('sloth.taxonomies') ?? []),
            ];
        } catch (\Throwable) {
            return ['models' => [], 'taxonomies' => []];
        }
    }
}
