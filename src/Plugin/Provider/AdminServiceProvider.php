<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Core\ServiceProvider;
use Sloth\Facades\Configure;

/**
 * Service provider for admin-related functionality.
 *
 * Handles:
 * - Hiding WordPress core/plugin/theme update notifications
 * - Admin menu cleanup (removing duplicate PHP pages)
 * - Layotter admin styling
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class AdminServiceProvider extends ServiceProvider
{
    /**
     * Register admin hooks and filters.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'admin_menu' => ['callback' => fn() => $this->cleanupAdminMenu(), 'priority' => 20],
            'admin_head' => fn() => $this->renderLayotterStyles(),
        ];
    }

    /**
     * Register admin filters.
     *
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        $filters = [];

        if (Configure::read('core.hide_updates')) {
            $filters['pre_site_transient_update_core'] = fn($t) => $this->hideUpdates($t);
        }

        if (Configure::read('plugins.hide_updates')) {
            $filters['pre_site_transient_update_plugins'] = fn($t) => $this->hideUpdates($t);
        }

        if (Configure::read('themes.hide_updates')) {
            $filters['pre_site_transient_update_themes'] = fn($t) => $this->hideUpdates($t);
        }

        return $filters;
    }

    /**
     * Hide WordPress update notifications.
     *
     * @since 1.0.0
     *
     * @param mixed $value
     *
     * @return object Fake update response with current time and WP version
     */
    public function hideUpdates(mixed $value = null): object
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

    /**
     * Render Layotter admin styles.
     *
     * @since 1.0.0
     */
    public function renderLayotterStyles(): void
    {
        echo '<style>
.layotter-preview { border-collapse: collapse; }
.layotter-preview th, .layotter-preview td { text-align: left !important; vertical-align: top; }
.layotter-preview th { padding-right: 10px; }
.layotter-preview tr:nth-child(even), .layotter-preview tr:nth-child(even) { background: #eee; }
td.media-icon img[src$=".svg"], img[src$=".svg"].attachment-post-thumbnail { width: 100% !important; height: auto !important; }
.media-icon img[src$=".svg"] { width: 60px; }
</style>';
    }
}
