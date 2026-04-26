<?php

declare(strict_types=1);

namespace Sloth\Debug\Collectors;

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
                return ['field_groups' => []];
            }

            $postId = get_the_ID();
            if (!$postId) {
                return ['field_groups' => []];
            }

            $fieldGroups = collect(acf_get_field_groups(['post_id' => $postId]))
                ->pluck('title')
                ->toArray();
        } catch (\Throwable) {
            $fieldGroups = [];
        }

        return [
            'field_groups' => $fieldGroups,
        ];
    }

    public function getName(): string
    {
        return 'acf';
    }

    public function getWidgets(): array
    {
        return [
            'acf' => [
                'icon' => '📦',
                'widget' => 'PhpDebugBar.Widget',
                'map' => 'acf',
                'attrs' => [
                    'title' => 'ACF',
                ],
            ],
        ];
    }
}