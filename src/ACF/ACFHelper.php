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
        add_action('init', $this->addFilters(...));
    }

    /**
     * Add ACF filters.
     *
     * @since 1.0.0
     */
    final public function addFilters(): void
    {
        add_action('admin_init', $this->autoSyncAcfFields(...));
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
            if ($private) {
                continue;
            }

            if ($local !== 'json') {
                continue;
            }

            if (
                !$group['ID'] || $modified > get_post_modified_time('U', true, $group['ID'], true)
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
