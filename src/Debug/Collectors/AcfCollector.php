<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

use DebugBar\DataCollector\AssetProvider;
use DebugBar\DataCollector\DataCollector;
use DebugBar\DataCollector\Renderable;

/**
 * Collector for ACF field groups on the current page.
 */
class AcfCollector extends DataCollector implements Renderable
{
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


    public function getName(): string
    {
        return 'acf';
    }

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
