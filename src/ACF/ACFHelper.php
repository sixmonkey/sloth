<?php

declare(strict_types=1);

namespace Sloth\ACF;

use Sloth\Configure\Configure;
use Sloth\Field\Image;

/**
 * Helper class for Advanced Custom Fields integration.
 *
 * @since 1.0.0
 */
class ACFHelper
{
    /**
     * Add ACF filters.
     *
     * @since 1.0.0
     */
    public function addFilters(): void
    {
        if (\Configure::read('layotter_prepare_fields') == 2) {
            add_filter('acf/format_value/type=image', [$this, 'load_image'], 10, 3);
        }
        add_action('admin_init', $this->autoSyncAcfFields(...));
    }

    /**
     * @param mixed $value
     * @param mixed $post_id
     * @param mixed $field
     */
    public function load_image(mixed $value, mixed $post_id, mixed $field): ?Image
    {
        if (str_starts_with($field['_name'], '_qundg')) {
            return $value;
        }

        $id = is_array($value) ? (int) $value['ID'] : $value;

        return $id ? new Image($id) : null;
    }

    /**
     * Auto-sync ACF JSON field groups.
     *
     * @since 1.0.0
     */
    public function autoSyncAcfFields(): void
    {
        $autosyncAcf = Configure::read('autosync_acf');
        if (
            !function_exists('acf_get_field_groups')
            || ! $this->isDevEnv()
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

    private function isDevEnv(): bool
    {
        return in_array(env('WP_ENV', ''), ['development', 'develop', 'dev'], true);
    }
}
