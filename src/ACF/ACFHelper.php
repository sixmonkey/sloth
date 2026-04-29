<?php

declare(strict_types=1);

namespace Sloth\ACF;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Field\Image;

/**
 * Helper class for Advanced Custom Fields integration.
 *
 * @since 1.0.0
 */
class ACFHelper
{
    /**
     * Load an image from ACF field value.
     *
     * @param mixed $value The ACF field value
     * @return Image|null The Image object or null
     * @throws BindingResolutionException
     * @since 1.0.0
     */
    public function loadImage($value, $post_id, $field)
    {
        return new Image($value);
    }

    /**
     * Auto-sync ACF JSON field groups.
     *
     * @since 1.0.0
     */
    public function autoSyncAcfFields(): void
    {
        $autosyncAcf = config('autosync_acf');
        if (
            !function_exists('acf_get_field_groups')
            || ! app()->isLocal()
            || $autosyncAcf === false
        ) {
            return;
        }

        $groups = acf_get_field_groups();

        if (empty($groups)) {
            return;
        }

        foreach ($groups as $group) {
            $local = acf_maybe_get($group, 'local', false);
            $modified = acf_maybe_get($group, 'modified', 0);
            $private = acf_maybe_get($group, 'private', false);
            if ($private) {
                continue;
            }

            if ($local !== 'json') {
                continue;
            }

            if (
                ! $group['ID'] || $modified > get_post_modified_time('U', true, $group['ID'], true)
            ) {
                acf_disable_filters();
                acf_enable_filter('local');
                acf_update_setting('json', false);
                $group['fields'] = acf_get_fields($group);
                $group = acf_import_field_group($group);
            }
        }
    }

}
