<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Facades\Configure;

/**
 * Service provider for admin-related functionality.
 *
 * Handles:
 * - Hiding WordPress core/plugin/theme update notifications
 * - Admin menu cleanup (removing duplicate PHP pages)
 * - Layotter admin styling
 *
 * ## Update Notifications
 *
 * Configure via config:
 * - `core.hide_updates` - Hide WordPress core update notifications
 * - `plugins.hide_updates` - Hide plugin update notifications
 * - `themes.hide_updates` - Hide theme update notifications
 *
 * ## Admin Menu Cleanup
 *
 * Removes duplicate entries from the admin menu that point to PHP files
 * with the same path (common when multiple plugins register the same page).
 *
 * ## Layotter Styling
 *
 * Injects CSS for improved Layotter preview tables and SVG media display.
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class AdminServiceProvider
{
    /**
     * Register admin hooks and filters.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        add_action('admin_menu', $this->cleanupAdminMenu(...), 20);

        add_action('admin_head', function (): void {
            echo '<style>
.layotter-preview { border-collapse: collapse; }
.layotter-preview th, .layotter-preview td { text-align: left !important; vertical-align: top; }
.layotter-preview th { padding-right: 10px; }
.layotter-preview tr:nth-child(even), .layotter-preview tr:nth-child(even) { background: #eee; }
td.media-icon img[src$=".svg"], img[src$=".svg"].attachment-post-thumbnail { width: 100% !important; height: auto !important; }
.media-icon img[src$=".svg"] { width: 60px; }
</style>';
        });

        if (Configure::read('core.hide_updates')) {
            add_filter('pre_site_transient_update_core', $this->hideUpdates(...));
        }

        if (Configure::read('plugins.hide_updates')) {
            add_filter('pre_site_transient_update_plugins', $this->hideUpdates(...));
        }

        if (Configure::read('themes.hide_updates')) {
            add_filter('pre_site_transient_update_themes', $this->hideUpdates(...));
        }
    }

    /**
     * Hide WordPress update notifications.
     *
     * Returns a fake update response object to suppress WordPress update
     * notifications. This prevents users from seeing update nags for
     * managed WordPress installations.
     *
     * @since 1.0.0
     *
     * @return object Fake update response with current time and WP version
     */
    public function hideUpdates(): object
    {
        global $wpVersion;

        return (object) [
            'last_checked' => time(),
            'version_checked' => $wpVersion,
        ];
    }

    /**
     * Clean up admin menu by removing duplicate PHP pages.
     *
     * Iterates through the admin menu and removes entries that point
     * to the same PHP file as a previously seen entry. This commonly
     * happens when multiple plugins register the same admin page.
     *
     * Only processes menu items with `.php` extensions.
     *
     * @since 1.0.0
     */
    public function cleanupAdminMenu(): void
    {
        global $menu;
        $used = [];
        foreach ($menu as $offset => $menuItem) {
            $pi = pathinfo((string) $menuItem[2], PATHINFO_EXTENSION);
            if (!preg_match('/^php/', $pi)) {
                continue;
            }

            if (in_array($menuItem[2], $used, true)) {
                unset($menu[$offset]);
                continue;
            }

            $used[] = $menuItem[2];
        }
    }
}
