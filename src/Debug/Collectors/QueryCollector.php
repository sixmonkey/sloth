<?php

declare(strict_types=1);
namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use Throwable;

/**
 * Database Query Collector.
 *
 * Collects and displays database queries executed during
 * the current request, including both Eloquent and
 * WordPress ($wpdb) queries.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class QueryCollector extends DataCollector implements Renderable, AssetProvider
{
    /**
     * Threshold in milliseconds for marking queries as slow.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private const SLOW_THRESHOLD_MS = 100;

    /**
     * Cached query results.
     *
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $cachedQueries = null;

    /**
     * Collect the query statistics.
     *
     * Aggregates all database queries from Eloquent and $wpdb,
     * sorted by execution time.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> the collected data
     */
    public function collect(): array
    {
        $queries = $this->getAllQueries();
        $count = count($queries);
        $totalTime = $count > 0 ? round(array_sum(array_column($queries, 'time')), 2) : 0;

        $statements = [];

        foreach ($queries as $q) {
            $statements[] = [
                'sql'           => $q['sql'],
                'time'          => $q['time'],
                'params'        => $q['params'] ?? [],
                'source'        => $q['source'] ?? '',
                'is_success'    => $q['is_success'] ?? true,
                'error_message' => $q['error_message'] ?? null,
            ];
        }

        return [
            'nb_statements'            => $count,
            'accumulated_duration'     => $totalTime,
            'accumulated_duration_str' => round($totalTime, 2) . 'ms',
            'statements'               => $statements,
        ];
    }

    /**
     * Get all queries merged and sorted by execution time.
     *
     * Cached on first call to avoid repeated DB fetches
     * and sorting.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>> the merged and sorted queries
     */
    private function getAllQueries(): array
    {
        if ($this->cachedQueries !== null) {
            return $this->cachedQueries;
        }

        $queries = array_merge(
            $this->getEloquentQueries(),
            $this->getWpdbQueries(),
        );

        usort($queries, fn ($a, $b) => $b['time'] <=> $a['time']);

        return $this->cachedQueries = $queries;
    }

    /**
     * Get all Eloquent queries from the current connection.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>> the Eloquent queries
     */
    private function getEloquentQueries(): array
    {
        try {
            if (!class_exists(\Illuminate\Database\Eloquent\Model::class)) {
                return [];
            }

            $connection = \Illuminate\Database\Eloquent\Model::resolveConnection();

            if (!$connection || !$connection->logging()) {
                return [];
            }

            $queries = [];

            foreach ($connection->getQueryLog() as $q) {
                $queries[] = [
                    'sql'           => $q['query'],
                    'time'          => round($q['time'], 2),
                    'params'        => $q['bindings'] ?? [],
                    'source'        => 'Eloquent',
                    'is_success'    => true,
                    'error_message' => null,
                ];
            }

            return $queries;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get all WordPress database queries from $wpdb.
     *
     * Requires the SAVEQUERIES constant to be enabled.
     *
     * @since 1.0.0
     *
     * @return array<int, array<string, mixed>> the WordPress queries
     */
    private function getWpdbQueries(): array
    {
        $queries = [];

        try {
            if (!defined('SAVEQUERIES') || !SAVEQUERIES) {
                return [];
            }

            global $wpdb;

            if (!$wpdb || !isset($wpdb->queries)) {
                return [];
            }

            foreach ($wpdb->queries as $q) {
                $caller = isset($q[2]) ? (string) $q[2] : '';
                $source = $caller ? 'WPDB — ' . $this->formatWpdbCaller($caller) : 'WPDB';
                $queries[] = [
                    'sql'           => $q[0],
                    'time'          => round($q[1] * 1000, 2),
                    'params'        => [],
                    'source'        => $source,
                    'is_success'    => true,
                    'error_message' => null,
                ];
            }
        } catch (Throwable) {
        }

        return $queries;
    }

    /**
     * Format a WPDB caller string into a readable file:line reference.
     *
     * @param  string $caller the raw caller string from $wpdb->queries
     * @return string formatted caller reference
     */
    private function formatWpdbCaller(string $caller): string
    {
        $caller = str_replace('\\', '/', $caller);

        if (preg_match('#([A-Za-z0-9._-]+\.php):(\d+)#', $caller, $m)) {
            return $m[1] . ':' . $m[2];
        }

        return trim($caller);
    }

    /**
     * Get the collector name.
     *
     * @since 1.0.0
     *
     * @return string the collector identifier
     */
    public function getName(): string
    {
        return 'queries';
    }

    /**
     * Get the widgets for this collector.
     *
     * Returns the debug bar widget configuration for
     * displaying database queries.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed> the widget configuration
     */
    public function getWidgets(): array
    {
        return [
            'queries' => [
                'icon'    => 'database',
                'tooltip' => 'Database Queries',
                'widget'  => 'PhpDebugBar.Widgets.SlothQueriesWidget',
                'map'     => 'queries',
                'default' => '{}',
            ],
            'queries:badge' => [
                'map'     => 'queries.nb_statements',
                'default' => '0',
            ],
        ];
    }

    /**
     * Register custom JS widget that adds a Source column.
     *
     * @return array<string, mixed>
     */
    public function getAssets(): array
    {
        return [
            'css' => [
                __DIR__ . '/../resources/sloth-queries-widget.css',
            ],
            'inline_js' => [
                file_get_contents(__DIR__ . '/../resources/sloth-queries-widget.js'),
            ],
        ];
    }
}
