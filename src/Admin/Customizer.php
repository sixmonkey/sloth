<?php

declare(strict_types=1);

namespace Sloth\Admin;

use Sloth\Facades\View;
use Sloth\Singleton\Singleton;

/**
 * Admin Customizer class for WordPress admin customization.
 *
 * @since 1.0.0
 */
class Customizer extends Singleton
{
    /**
     * Completed actions.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $done = [];

    /**
     * Meta boxes to remove.
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    public static array $removeMetaBoxes = [];

    /**
     * TinyMCE styles.
     *
     * @since 1.0.0
     * @var array<string, array<string>>
     */
    public static array $tinymceStyles = [];

    /**
     * TinyMCE buttons to remove.
     *
     * @since 1.0.0
     * @var array<int, array<string>>
     */
    public static array $tinymceRemoveButtons = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
    ];

    /**
     * TinyMCE buttons to add.
     *
     * @since 1.0.0
     * @var array<int, array<string, mixed>>
     */
    public static array $tinymceAddButtons = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
    ];

    /**
     * Admin bar menu items to remove.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $removeAdminBarMenus = [];

    /**
     * Menu separators to add.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $addMenuSeparators = [];

    /**
     * Post list columns to add.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    public static array $addPostListColumns = [];

    /**
     * SEO features to use.
     *
     * @since 1.0.0
     * @var array<string, bool>
     */
    public static array $useSeoFeatures = [];

    /**
     * Meta description as excerpt settings.
     *
     * @since 1.0.0
     * @var array<string, bool>
     */
    public static array $useMetaDescriptionAsExcerpt = [];

    /**
     * Taxonomies to disable.
     *
     * @since 1.0.0
     * @var array<string, bool>
     */
    public static array $disableTaxonomies = [];

    /**
     * Menu items to move.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public static array $moveMenuItems = [];

    /**
     * Menu items to remove.
     *
     * @since 1.0.0
     * @var array<string>
     */
    public static array $removeMenuItems = [];

    /**
     * Menu items to add.
     *
     * @since 1.0.0
     * @var array<array<string, mixed>>
     */
    public static array $addMenuItems = [];

    /**
     * Menu items to rename.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public static array $renameMenuItems = [];

    /**
     * Submenu items to add.
     *
     * @since 1.0.0
     * @var array<array<string, mixed>>
     */
    public static array $addSubmenuItems = [];

    /**
     * Custom dashboard items.
     *
     * @since 1.0.0
     * @var array<array<string, string>>
     */
    public static array $customDashboard = [];

    /**
     * Boot the customizer hooks.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        add_action(
            'admin_menu',
            function (): void {
                remove_filter('update_footer', 'core_update_footer');
            }
        );
    }

    /**
     * Join arrays together.
     *
     * @param array<string> $existing Existing items
     * @param mixed $new New items to add
     *
     * @return array<string>
     * @since 1.0.0
     *
     */
    private static function joinArray(array $existing, mixed $new): array
    {
        if (is_array($new)) {
            return array_merge($existing, $new);
        }

        $existing[] = $new;

        return $existing;
    }

    /**
     * Check if an action has been done.
     *
     * @param string $what Action identifier
     * @since 1.0.0
     *
     */
    private static function done(string $what): bool
    {
        if (in_array($what, self::$done, true)) {
            return true;
        }

        self::$done[] = $what;

        return false;
    }

    /**
     * Remove a post meta box.
     *
     * @param string $postType Post type
     * @param string $box Meta box ID
     * @since 1.0.0
     *
     */
    public static function removePostMetaBox(string $postType, string $box): void
    {
        if (!isset(self::$removeMetaBoxes[$postType])) {
            self::$removeMetaBoxes[$postType] = [];
        }

        self::$removeMetaBoxes[$postType] = self::joinArray(self::$removeMetaBoxes[$postType], $box);

        if (!self::done('remove_post_meta_box')) {
            add_action(
                'admin_head',
                function (): void {
                    foreach (self::$removeMetaBoxes as $postType => $boxes) {
                        foreach ($boxes as $box) {
                            remove_meta_box($box, $postType, 'normal');
                            remove_meta_box($box, $postType, 'side');
                        }
                    }
                }
            );
        }
    }

    /**
     * Remove a TinyMCE button.
     *
     * @param int $row Row number (1-4)
     * @param string $button Button name
     * @since 1.0.0
     *
     */
    public static function tinymceRemoveButton(int $row, string $button): void
    {
        self::$tinymceRemoveButtons[$row] = self::joinArray(self::$tinymceRemoveButtons[$row], $button);

        $filter = $row === 1 ? 'mce_buttons' : 'mce_buttons_' . $row;

        if (!self::done('tinymce_remove_buttons_' . $filter)) {
            add_filter(
                $filter,
                function (array $buttons) use ($row): array {
                    if (!ctype_digit(substr(current_filter(), -1))) {
                        $row = 1;
                    }

                    $removeButtons = self::$tinymceRemoveButtons[$row];
                    foreach ($removeButtons as $buttonName) {
                        $key = array_search($buttonName, $buttons, true);
                        if ($key !== false) {
                            unset($buttons[$key]);
                        }
                    }

                    return $buttons;
                }
            );
        }
    }

    /**
     * Add a TinyMCE button.
     *
     * @param int $row Row number (1-4)
     * @param string $button Button name
     * @param bool|int $position Position to insert (false for end)
     * @since 1.0.0
     *
     */
    public static function tinymceAddButton(int $row, string $button, bool|int $position = false): void
    {
        self::$tinymceAddButtons[$row][] = [
            'name' => $button,
            'position' => $position,
        ];
        $filter = $row === 1 ? 'mce_buttons' : 'mce_buttons_' . $row;

        if (!self::done('tinymce_add_buttons_' . $filter)) {
            add_filter(
                $filter,
                function (array $buttons) use ($row): array {
                    if (!ctype_digit(substr(current_filter(), -1))) {
                        $row = 1;
                    }

                    $addButtons = self::$tinymceAddButtons[$row];
                    foreach ($addButtons as $button) {
                        if ($button['position'] === false) {
                            $buttons[] = $button['name'];
                        } else {
                            array_splice(
                                $buttons,
                                $button['position'],
                                0,
                                $button['name']
                            );
                        }
                    }

                    return $buttons;
                }
            );
        }
    }

    /**
     * Clean up the WordPress dashboard.
     *
     * @since 1.0.0
     */
    public static function cleanDashboard(): void
    {
        remove_action('welcome_panel', 'wp_welcome_panel');

        add_action(
            'wp_dashboard_setup',
            function (): void {
                remove_meta_box('dashboard_browser_nag', 'dashboard', 'normal');
                remove_meta_box('dashboard_right_now', 'dashboard', 'normal');
                remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
                remove_meta_box('dashboard_incoming_links', 'dashboard', 'normal');
                remove_meta_box('dashboard_plugins', 'dashboard', 'normal');
                remove_meta_box('dashboard_quick_press', 'dashboard', 'side');
                remove_meta_box('dashboard_recent_drafts', 'dashboard', 'side');
                remove_meta_box('dashboard_primary', 'dashboard', 'side');
                remove_meta_box('dashboard_secondary', 'dashboard', 'side');
                remove_meta_box('dashboard_activity', 'dashboard', 'normal');
            }
        );

        add_action(
            'admin_head',
            function (): void {
                $currentScreen = get_current_screen();
                if ($currentScreen->base === 'dashboard') {
                    ?>
                        <style type="text/css">
                            .empty-container {
                                border: none !important;
                                height: auto !important;
                            }

                            .empty-container:after {
                                content: '' !important;
                            }
                        </style>
                        <?php
                }
            }
        );
    }

    /**
     * Remove an admin bar menu item.
     *
     * @param string $item Menu item ID
     * @since 1.0.0
     *
     */
    public static function removeAdminBarItem(string $item): void
    {
        self::$removeAdminBarMenus = self::joinArray(self::$removeAdminBarMenus, $item);

        if (!self::done('remove_admin_bar_items')) {
            add_action(
                'wp_before_admin_bar_render',
                function (): void {
                    global $wpAdminBar;
                    foreach (self::$removeAdminBarMenus as $item) {
                        $wpAdminBar->remove_menu($item);
                    }
                }
            );
        }
    }

    /**
     * Clean up the user profile edit form.
     *
     * @since 1.0.0
     */
    public static function cleanProfileEditForm(): void
    {
        add_action(
            'admin_head',
            function (): void {
                $currentScreen = get_current_screen();
                if ($currentScreen->base === 'profile' || $currentScreen->base === 'profile-user') {
                    ?>
                        <style type="text/css">
                            #your-profile h2,
                            #your-profile .user-user-login-wrap,
                            #your-profile .user-description-wrap,
                            #your-profile .user-profile-picture,
                            #your-profile .user-nickname-wrap,
                            #your-profile .user-url-wrap,
                            #your-profile .user-rich-editing-wrap,
                            #your-profile .user-admin-color-wrap,
                            #your-profile .user-comment-shortcuts-wrap,
                            #your-profile .show-admin-bar.user-admin-bar-front-wrap {
                                display: none;
                            }
                        </style>
                        <?php
                }
            }
        );
    }

    /**
     * Add a menu separator.
     *
     * @param string $after Menu item to place separator after
     * @since 1.0.0
     *
     */
    public static function addMenuSeparator(string $after): void
    {
        self::$addMenuSeparators = self::joinArray(self::$addMenuSeparators, $after);

        if (!self::done('add_menu_separators')) {
            add_action(
                'admin_menu',
                function (): void {
                    global $menu;
                    $menu = array_values($menu);

                    foreach ($menu as $offset => $section) {
                        if (str_starts_with((string) $section[2], 'separator')) {
                            array_splice($menu, $offset, 1);
                        }
                    }

                    $index = 1;
                    foreach (self::$addMenuSeparators as $after) {
                        foreach ($menu as $offset => $section) {
                            if ($section[2] === $after) {
                                array_splice(
                                    $menu,
                                    $offset + 1,
                                    0,
                                    [['', 'read', 'separator' . $index++, '', 'wp-menu-separator']]
                                );
                                break;
                            }
                        }
                    }
                },
                9999999
            );
        }
    }

    /**
     * Move a menu item.
     *
     * @param string $move Menu item to move
     * @param string $after Menu item to place it after
     * @since 1.0.0
     *
     */
    public static function moveMenuItem(string $move, string $after): void
    {
        self::$moveMenuItems[$move] = $after;

        if (!self::done('move_menu_items')) {
            add_action(
                'admin_menu',
                function (): void {
                    global $menu;
                    $menu = array_values($menu);

                    foreach (self::$moveMenuItems as $move => $after) {
                        $toBeMoved = false;

                        foreach ($menu as $offset => $section) {
                            if ($section[2] === $move) {
                                $toBeMoved = $section;
                                array_splice($menu, $offset, 1);
                                break;
                            }
                        }

                        if ($toBeMoved) {
                            foreach ($menu as $offset => $section) {
                                if ($section[2] === $after) {
                                    array_splice($menu, $offset + 1, 0, [$toBeMoved]);
                                    break;
                                }
                            }
                        }
                    }
                },
                9999998
            );
        }
    }

    /**
     * Remove a menu item.
     *
     * @param string $url Menu item URL
     * @since 1.0.0
     *
     */
    public static function removeMenuItem(string $url): void
    {
        self::$removeMenuItems = self::joinArray(self::$removeMenuItems, $url);

        if (!self::done('remove_menu_items')) {
            add_action(
                'admin_menu',
                function (): void {
                    global $menu;
                    $menu = array_values($menu);

                    foreach ($menu as $offset => $section) {
                        if (in_array($section[2], self::$removeMenuItems, true)) {
                            unset($menu[$offset]);
                        }
                    }
                },
                9999
            );
        }
    }

    /**
     * Rename a menu item.
     *
     * @param string $url Menu item URL
     * @param string $newName New display name
     * @since 1.0.0
     *
     */
    public static function renameMenuItem(string $url, string $newName): void
    {
        self::$renameMenuItems[$url] = $newName;

        if (!self::done('rename_menu_items')) {
            add_action(
                'admin_menu',
                function (): void {
                    global $menu;

                    foreach ($menu as &$section) {
                        if (array_key_exists($section[2], self::$renameMenuItems)) {
                            $section[0] = self::$renameMenuItems[$section[2]];
                        }
                    }
                },
                9999996
            );
        }
    }

    /**
     * Add a top-level menu item.
     *
     * @param string $url Menu item URL
     * @param string $after Menu item to place it after
     * @param string $title Display title
     * @param string $capability Required capability
     * @param string $icon Dashicons icon class
     * @since 1.0.0
     *
     */
    public static function addMenuItem(
        string $url,
        string $after,
        string $title,
        string $capability,
        string $icon = 'dashicons-admin-post'
    ): void {
        self::$addMenuItems[] = [
            'url' => $url,
            'after' => $after,
            'title' => $title,
            'capability' => $capability,
            'icon' => $icon,
        ];
        self::moveMenuItem($url, $after);

        if (!self::done('add_menu_items')) {
            add_action(
                'admin_menu',
                function (): void {
                    foreach (self::$addMenuItems as $item) {
                        add_menu_page(
                            $item['url'],
                            $item['title'],
                            $item['capability'],
                            $item['url'],
                            null,
                            $item['icon']
                        );
                    }
                },
                9999996
            );
        }
    }

    /**
     * Add a submenu item.
     *
     * @param string $url Submenu item URL
     * @param string $parent Parent menu item URL
     * @param string $title Display title
     * @param string $capability Required capability
     * @since 1.0.0
     *
     */
    public static function addSubmenuItem(string $url, string $parent, string $title, string $capability): void
    {
        self::$addSubmenuItems[] = [
            'url' => $url,
            'parent' => $parent,
            'title' => $title,
            'capability' => $capability,
        ];

        if (!self::done('add_submenu_items')) {
            add_action(
                'admin_menu',
                function (): void {
                    foreach (self::$addSubmenuItems as $item) {
                        add_submenu_page(
                            $item['parent'],
                            $item['title'],
                            $item['title'],
                            $item['capability'],
                            $item['url']
                        );
                    }
                },
                9999997
            );
        }
    }

    /**
     * Load scripts asynchronously.
     *
     * @since 1.0.0
     */
    public static function loadScriptsAsynchronously(): void
    {
        add_filter(
            'clean_url',
            function (string $url): string {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if ($extension === 'js') {
                    $url .= "' async='async";
                }

                return $url;
            },
            11,
            1
        );
    }

    /**
     * Add a custom dashboard item.
     *
     * @param string $url Item URL
     * @param string $text Display text
     * @param string $icon Dashicons icon class
     * @since 1.0.0
     *
     */
    public static function addCustomDashboardItem(
        string $url,
        string $text,
        string $icon = 'dashicons-admin-post'
    ): void {
        self::$customDashboard[] = [
            'url' => $url,
            'text' => $text,
            'icon' => $icon,
        ];

        if (!self::done('custom_dashboard')) {
            add_action(
                'wp_dashboard_setup',
                function (): void {
                    add_meta_box(
                        'qundg-dashboard',
                        'Start',
                        self::dashboardWelcome(...),
                        'dashboard',
                        'normal'
                    );
                }
            );
        }
    }

    /**
     * Render the dashboard welcome box.
     *
     * @since 1.0.0
     */
    public static function dashboardWelcome(): void
    {
        ?>
        <style type="text/css">
            #qundg-dashboard {
                display: none;
            }

            .qundg-dashboard-menu-item {
                padding-top: 6px;
                margin-left: 26px;
            }
        </style>
        <script>
            jQuery(document).ready(function ($) {
                $('#qundg-dashboard').show().removeClass('postbox').find('.hndle, .handlediv').remove();
            });
        </script>
        <h2>Hallo <?php
            echo esc_html(ucwords(wp_get_current_user()->data->display_name)); ?></h2>
        <p>
            Schön, dass du da bist! Was nun?
        </p>
        <ul>
            <?php
            foreach (self::$customDashboard as $item) {
                ?>
                <li>
                    <div class="icon16 dashicons <?php
                    echo esc_attr($item['icon']); ?>"></div>
                    <p class="qundg-dashboard-menu-item">
                        <a href="<?php
                        echo esc_url($item['url']); ?>"><?php
                            echo esc_html($item['text']); ?></a>
                    </p>
                </li>
                <?php
            }
        ?>
        </ul>
        <?php
    }

    /**
     * Render the footer for WordPress admin.
     *
     * @since 1.0.0
     */
    public function renderFooter()
    {
        $versions = [
            'WordPress ' . wp_get_wp_version(),
            'Sloth ' . app()->version(),
        ];

        if (file_exists(DIR_ROOT . DS . '.version')) {
            $appVersion = file_get_contents(DIR_ROOT . DS . '.version');
            $versions[] = 'App ' . $appVersion;
        }

        $data = [
            'versions' => implode(' | ', $versions),
        ];

        $view = View::make('Admin.footer');

        return $view
                ->with($data)
                ->render();
    }

    /**
     * Hide WordPress update notifications.
     *
     * @param mixed $value
     *
     * @return object Fake update response with current time and WP version
     * @since 1.0.0
     *
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
}
