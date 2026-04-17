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
 * - Corcel/Eloquent database queries via enableQueryLog()
 *
 * ## Query tracking
 *
 * Corcel does not fire Illuminate QueryExecuted events reliably outside
 * Laravel, and Connection::listen() does not work in this context.
 * Instead, we use Connection::enableQueryLog() after Database::connect()
 * and read the log when the panel renders.
 *
 * In Core\Sloth::connectCorcel():
 *
 *     if ($this->container->isLocal()) {
 *         \Corcel\Model\Post::resolveConnection()->enableQueryLog();
 *     }
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
     * @return string HTML for the panel content.
     * @since 1.0.0
     */
    #[\Override]
    public function getPanel(): string
    {
        try {
            $currentLayout = app('sloth.current_layout') ?? 'none';
        } catch (\Throwable) {
            $currentLayout = 'none';
        }

        $h = new Hierarchy();

        return View::make('Debugger.sloth-bar-panel')->with([
            'currentTemplate' => $currentLayout,
            'templates' => $h->templates(),
            'performance' => $this->getPerformanceData(),
            'container' => $this->getContainerData(),
            'wordpress' => $this->getWordPressData(),
            'acf' => $this->getAcfData(),
            'sloth' => $this->getSlothData(),
            'queries' => $this->getQueryData(),
        ])->render();
    }

    /**
     * Render the tab label shown in the Tracy Bar.
     *
     * Shows a query count badge to help spot N+1 problems at a glance:
     * green ≤10, orange ≤20, red >20.
     *
     * @return string HTML for the tab.
     * @since 1.0.0
     */
    #[\Override]
    public function getTab(): string
    {
        $queryData = $this->getQueryData();
        $queryCount = $queryData['count'];

        $badge = $queryCount > 0
            ? sprintf(
                '<span style="background:%s;color:#fff;border-radius:3px;padding:1px 5px;font-size:10px;margin-left:3px;">%d</span>',
                $queryCount > 20 ? '#ef4444' : ($queryCount > 10 ? '#f97316' : '#22c55e'),
                $queryCount
            )
            : '';

        return '<span title="SLOTH">🦥' . $badge . '</span>';
    }

    /**
     * Collect performance metrics for the current request.
     *
     * @return array{memory: string, peak_memory: string, time: string}
     * @since 1.0.0
     */
    private function getPerformanceData(): array
    {
        return [
            'memory' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB',
            'peak_memory' => round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB',
            'time' => round((microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true))) * 1000) . ' ms',
        ];
    }

    /**
     * Collect Sloth container state information.
     *
     * @return array{providers: int, bindings: int, environment: string}
     * @since 1.0.0
     */
    private function getContainerData(): array
    {
        try {
            return [
                'providers' => count(app()->getLoadedProviders()),
                'bindings' => count(app()->getBindings()),
                'environment' => app()->environment(),
            ];
        } catch (\Throwable) {
            return ['providers' => 0, 'bindings' => 0, 'environment' => 'unknown'];
        }
    }

    /**
     * Collect WordPress context for the current request.
     *
     * @return array{post_type: string, queried_object_id: int, template_slug: string, hooks: int, is_admin: string}
     * @since 1.0.0
     */
    private function getWordPressData(): array
    {
        try {
            global $wp_filter;

            return [
                'post_type' => get_post_type() ?: 'none',
                'queried_object_id' => get_queried_object_id(),
                'template_slug' => get_page_template_slug() ?: 'default',
                'hooks' => is_array($wp_filter) ? count($wp_filter) : 0,
                'is_admin' => is_admin() ? 'yes' : 'no',
            ];
        } catch (\Throwable) {
            return [
                'post_type' => 'unknown',
                'queried_object_id' => 0,
                'template_slug' => 'unknown',
                'hooks' => 0,
                'is_admin' => 'unknown',
            ];
        }
    }

    /**
     * Collect ACF field groups active on the current page.
     *
     * @return array<string>
     * @since 1.0.0
     */
    private function getAcfData(): array
    {
        try {
            if (!function_exists('acf_get_field_groups')) {
                return [];
            }

            return collect(acf_get_field_groups(['post_id' => get_the_ID()]))
                ->pluck('title')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Collect Sloth-specific debug data.
     *
     * @return array{models: array<string>, taxonomies: array<string>}
     * @since 1.0.0
     */
    private function getSlothData(): array
    {
        try {
            return [
                'models' => array_keys(app('sloth.models') ?? []),
                'taxonomies' => array_keys(app('sloth.taxonomies') ?? []),
            ];
        } catch (\Throwable) {
            return ['models' => [], 'taxonomies' => []];
        }
    }

    /**
     * Collect Corcel/Eloquent query data for this request.
     *
     * Reads from the Connection query log which must have been enabled
     * via Connection::enableQueryLog() after Database::connect().
     * Slow queries (>100ms) are flagged for easy identification.
     *
     * @return array{queries: array, count: int, total_time: float, slow: int}
     * @since 1.0.0
     */
    private function getQueryData(): array
    {
        try {
            $queries = collect(\Corcel\Model\Post::resolveConnection()->getQueryLog())
                ->map(fn($q) => [
                    'sql' => $q['query'],
                    'time' => round($q['time'], 2),
                    'connection' => 'default',
                ])
                ->toArray();
        } catch (\Throwable) {
            $queries = [];
        }

        $totalTime = round(array_sum(array_column($queries, 'time')), 2);
        $slowCount = count(array_filter($queries, fn($q) => $q['time'] > 100));

        return [
            'queries' => $queries,
            'count' => count($queries),
            'total_time' => $totalTime,
            'slow' => $slowCount,
        ];
    }
}
