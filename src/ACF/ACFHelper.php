<?php

declare(strict_types=1);

namespace Sloth\ACF;

use Sloth\Configure\Configure;
use Sloth\Field\Image;
use Sloth\Singleton\Singleton;

/**
 * Helper class for Advanced Custom Fields integration.
 *
 * @since 1.0.0
 */
class ACFHelper extends Singleton
{
    /**
     * ACFHelper constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        add_action('init', [$this, 'addFilters']);
    }

    /**
     * Add ACF filters.
     *
     * @since 1.0.0
     *
     * @return void
     */
    final public function addFilters(): void
    {
        if (Configure::read('layotter_prepare_fields') == 2) {
            add_filter('acf/format_value/type=image', [$this, 'loadImage'], 10, 3);
        }
        add_action('admin_init', [$this, 'autoSyncAcfFields']);
    }

    /**
     * Load an image field value.
     *
     * @since 1.0.0
     *
     * @param mixed      $value    The field value
     * @param int       $postId   The post ID
     * @param array<string, mixed> $field    The field configuration
     *
     * @return mixed
     */
    final public function loadImage(mixed $value, int|string $postId, array $field): mixed
    {
        if (str_starts_with((string) ($field['_name'] ?? ''), '_qundg')) {
            return $value;
        }

        if ($value === false || $value === '' || $value === null) {
            return $value;
        }

        $id = is_array($value) ? (int) ($value['ID'] ?? 0) : (int) $value;

        if ($id === 0) {
            return $value;
        }

        return new Image($id);
    }

    /**
     * Auto-sync ACF JSON field groups.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function autoSyncAcfFields(): void
    {
        $autosyncAcf = Configure::read('autosync_acf');
        if (
            !function_exists('acf_get_field_groups')
            || !$GLOBALS['sloth::plugin']->isDevEnv()
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

            if ($private || $local !== 'json') {
                continue;
            }

            if (
                (!$private || $local === 'json')
                && (!$group['ID'] || $modified > get_post_modified_time('U', true, $group['ID'], true))
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
