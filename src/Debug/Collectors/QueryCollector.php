<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

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
class QueryCollector extends DataCollector implements Renderable
{
    /**
     * Threshold in milliseconds for marking queries as slow.
     *
     * @since 1.0.0
     * @var int
     */
    private const SLOW_THRESHOLD_MS = 100;

    /**
     * Collect the query statistics.
     *
     * Aggregates all database queries from Eloquent and $wpdb,
     * sorted by execution time.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        return [
            'queries' => $this->getAllQueries(),
            'count' => $this->getQueryCount(),
            'total_time' => $this->getTotalTime(),
            'slow' => $this->getSlowCount(),
        ];
    }

    /**
     * Get all queries merged and sorted by execution time.
     *
     * Merges Eloquent and WordPress database queries,
     * then sorts by execution time descending.
     *
     * @since 1.0.0
     * @return array<int, array<string, mixed>> The merged and sorted queries.
     */
    private function getAllQueries(): array
    {
        $queries = array_merge(
            $this->getEloquentQueries(),
            $this->getWpdbQueries()
        );

        usort($queries, fn($a, $b) => $b['time'] <=> $a['time']);

        return $queries;
    }

    /**
     * Get all Eloquent queries from the current connection.
     *
     * @since 1.0.0
     * @return array<int, array<string, mixed>> The Eloquent queries.
     */
    private function getEloquentQueries(): array
    {
        try {
            if (!class_exists(\Illuminate\Database\Eloquent\Model::class)) {
                return [];
            }

            $connection = \Illuminate\Database\Eloquent\Model::resolveConnection();
            if (!$connection) {
                return [];
            }

            return collect($connection->getQueryLog())
                ->map(fn($q) => [
                    'sql' => $q['query'],
                    'time' => round($q['time'], 2),
                    'source' => 'Eloquent',
                ])
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Get all WordPress database queries from $wpdb.
     *
     * Requires the SAVEQUERIES constant to be enabled.
     *
     * @since 1.0.0
     * @return array<int, array<string, mixed>> The WordPress queries.
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
                $queries[] = [
                    'sql' => $q[0],
                    'time' => round($q[1] * 1000, 2),
                    'source' => 'WPDB',
                ];
            }
        } catch (\Throwable) {
            // $wpdb not available
        }

        return $queries;
    }

    /**
     * Get the total number of queries executed.
     *
     * @since 1.0.0
     * @return int The query count.
     */
    private function getQueryCount(): int
    {
        return count($this->getAllQueries());
    }

    /**
     * Get the total execution time of all queries.
     *
     * @since 1.0.0
     * @return float The total time in milliseconds.
     */
    private function getTotalTime(): float
    {
        $queries = $this->getAllQueries();
        return round(array_sum(array_column($queries, 'time')), 2);
    }

    /**
     * Get the count of slow queries.
     *
     * Counts queries that exceed the slow query threshold.
     *
     * @since 1.0.0
     * @return int The number of slow queries.
     */
    private function getSlowCount(): int
    {
        return count(array_filter(
            $this->getAllQueries(),
            fn($q) => $q['time'] > self::SLOW_THRESHOLD_MS
        ));
    }

    /**
     * Get the collector name.
     *
     * @since 1.0.0
     * @return string The collector identifier.
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
     * @return array<string, mixed> The widget configuration.
     */
    public function getWidgets(): array
    {
        return [
            'queries' => [
                'icon' => 'database',
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'queries',
            ],
        ];
    }
}
