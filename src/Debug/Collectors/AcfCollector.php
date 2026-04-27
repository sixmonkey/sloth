<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * ACF Field Groups Collector.
 *
 * Collects and displays Advanced Custom Pro field groups
 * associated with the current post or page.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class AcfCollector extends DataCollector implements Renderable
{
    /**
     * Collect the ACF field groups data.
     *
     * Retrieves all ACF field groups attached to the current
     * post and returns them as key-value pairs.
     *
     * @since 1.0.0
     * @return array<string, mixed> The collected data.
     */
    public function collect(): array
    {
        try {
            if (!function_exists('acf_get_field_groups')) {
                return ['groups' => []];
            }

            $postId = get_the_ID();
            if (!$postId) {
                return ['groups' => []];
            }

            $fieldGroups = collect(acf_get_field_groups(['post_id' => $postId]))
                ->mapWithKeys(function ($group) {
                    return [$group['title'] => $group['key']];
                })
                ->toArray();
        } catch (\Throwable) {
            $fieldGroups = [];
        }

        return [
            'groups' => $fieldGroups,
            'count' => count($fieldGroups)
        ];
    }


    /**
     * Get the collector name.
     *
     * @since 1.0.0
     * @return string The collector identifier.
     */
    public function getName(): string
    {
        return 'acf';
    }

    /**
     * Get the widgets for this collector.
     *
     * Returns the debug bar widget configuration for
     * displaying ACF field groups.
     *
     * @since 1.0.0
     * @return array<string, mixed> The widget configuration.
     */
    public function getWidgets(): array
    {
        return [
            'acf' => array(
                'map' => 'acf.groups',
                'widget' => 'PhpDebugBar.Widgets.KVListWidget',
                'default' => '{}',
            ),
            'acf:badge' => [
                'map' => 'acf.count',
                'default' => 'null',
            ],
        ];
    }
}
