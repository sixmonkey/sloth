<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;
use DebugBar\DataCollector\VariableProvider;

/**
 * Collector for database queries: Eloquent + $wpdb.
 */
class QueryCollector extends DataCollector implements Renderable
{
    private const SLOW_THRESHOLD_MS = 100;

    public function collect(): array
    {
        return [
            'queries' => $this->getAllQueries(),
            'count' => $this->getQueryCount(),
            'total_time' => $this->getTotalTime(),
            'slow' => $this->getSlowCount(),
        ];
    }

    private function getAllQueries(): array
    {
        $queries = array_merge(
            $this->getEloquentQueries(),
            $this->getWpdbQueries()
        );

        usort($queries, fn($a, $b) => $b['time'] <=> $a['time']);

        return $queries;
    }

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

    private function getQueryCount(): int
    {
        return count($this->getAllQueries());
    }

    private function getTotalTime(): float
    {
        $queries = $this->getAllQueries();
        return round(array_sum(array_column($queries, 'time')), 2);
    }

    private function getSlowCount(): int
    {
        return count(array_filter(
            $this->getAllQueries(),
            fn($q) => $q['time'] > self::SLOW_THRESHOLD_MS
        ));
    }

    public function getName(): string
    {
        return 'queries';
    }

    public function getWidgets(): array
    {
        return [
            'queries' => [
                'widget' => 'PhpDebugBar.Widgets.SQLQueriesWidget',
                'map' => 'queries',
            ],
        ];
    }

    public function getVarDumperSetup(): void
    {
    }
}
