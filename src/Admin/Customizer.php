<?php

namespace Sloth\Admin;

use Sloth\Facades\View;
use Sloth\Singleton\Singleton;

class Customizer extends Singleton {

    protected static $instance;
    public static $done = [];
    public static $move_menu_items = [];
    public static $remove_admin_bar_menus = [];
    public static $remove_menu_items = [];
    public static $remove_meta_boxes = [];
    public static $rename_menu_items = [];
    public static $tinymce_add_buttons = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
    ];
    public static $tinymce_remove_buttons = [
        1 => [],
        2 => [],
        3 => [],
        4 => [],
    ];
    public static $tinymce_styles = [];
    public static $use_meta_description_as_excerpt = [];
    public static $use_seo_features = [];

    protected static $instance;

    /**
     * @param        $url
     * @param        $text
     * @param string $icon
     */
    public static function add_custom_dashboard_item($url, $text, $icon = 'dashicons-admin-post')
    {
        self::$custom_dashboard[] = [
            'url'  => $url,
            'text' => $text,
            'icon' => $icon,
        ];

        if (! self::done('custom_dashboard')) {
            add_action(
                'wp_dashboard_setup',
                function () {
                    add_meta_box(
                        'qundg-dashboard',
                        'Start',
                        [__CLASS__, 'dashboard_welcome'],
                        'dashboard',
                        'normal'
                    );
                }
            );
        }
    }


    /**
     * @param        $url
     * @param        $after
     * @param        $title
     * @param        $capability
     * @param string $icon
     */
    public static function add_menu_item($url, $after, $title, $capability, $icon = 'dashicons-admin-post')
    {
        self::$add_menu_items[] = [
            'url'        => $url,
            'after'      => $after,
            'title'      => $title,
            'capability' => $capability,
            'icon'       => $icon,
        ];
        self::move_menu_item($url, $after);

        if (! self::done('add_menu_items')) {
            add_action(
                'admin_menu',
                function () {
                    foreach (\Sloth\Admin\Customizer::$add_menu_items as $item) {
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
     * @param $after
     */
    public static function add_menu_separator($after)
    {
        self::$add_menu_separators = self::join_array(self::$add_menu_separators, $after);

        // jetzt eigene Trenner einsetzen
        if (! self::done('add_menu_separators')) {
            add_action(
                'admin_menu',
                function () {
                    global $menu;
                    $menu = array_values($menu); // sorgt dafür, dass Keys lückenlos nummeriert sind

                    // erst alle Trenner entfernen
                    foreach ($menu as $offset => $section) {
                        if (substr($section[2], 0, 9) == 'separator') {
                            array_splice($menu, $offset, 1);
                        }
                    }

                    // jetzt eigene Trenner einsetzen
                    $index = 1; // Trenner müssen in Wordpress durchnummeriert sein
                    foreach (\Sloth\Admin\Customizer::$add_menu_separators as $after) {
                        foreach ($menu as $offset => $section) {
                            if ($section[2] == $after) {
                                array_splice(
                                    $menu,
                                    $offset + 1,
                                    0,
                                    [['', 'read', 'separator' . $index ++, '', 'wp-menu-separator']]
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
     * @param $url
     * @param $parent
     * @param $title
     * @param $capability
     */
    public static function add_submenu_item($url, $parent, $title, $capability)
    {
        self::$add_submenu_items[] = [
            'url'        => $url,
            'parent'     => $parent,
            'title'      => $title,
            'capability' => $capability,
        ];

        if (! self::done('add_submenu_items')) {
            add_action(
                'admin_menu',
                function () {
                    foreach (\Sloth\Admin\Customizer::$add_submenu_items as $item) {
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
     * Add required hooks to WordPress
     */
    public function boot()
    {
        // Remove update message from footer
        add_action(
            'admin_menu',
            function () {
                remove_filter('update_footer', 'core_update_footer');
            }
        );

        // set footer text
        add_action(
            'admin_init',
            function () {
                add_filter(
                    'admin_footer_text',
                    [$this, 'renderFooter'],
                    999
                );
            }
        );
    }

    /**
     * Remove all boxes from dashboard
     */
    public static function clean_dashboard()
    {
        remove_action('welcome_panel', 'wp_welcome_panel');

        add_action(
            'wp_dashboard_setup',
            function () {
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
            function () {
                $current_screen = get_current_screen();
                if ($current_screen->base == 'dashboard') {
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
     * Clean up the user profile edit form
     */
    public static function clean_profile_edit_form()
    {
        add_action(
            'admin_head',
            function () {
                $current_screen = get_current_screen();
                if ($current_screen->base == 'profile' or $current_screen->base == 'profile-user') {
                    ?>
                    <style type="text/css">
                        #your-profile h2,
                        #your-profile .user-user-login-wrap, /* Login-Name (kann eh nicht geändert werden) */
                        #your-profile .user-description-wrap, /* Biographische Angaben */
                        #your-profile .user-profile-picture, /* Profilbild */
                        #your-profile .user-nickname-wrap, /* Spitzname */
                        #your-profile .user-url-wrap, /* Website */
                        #your-profile .user-rich-editing-wrap, /* WYSIWYG verwenden? */
                        #your-profile .user-admin-color-wrap, /* Farbschema */
                        #your-profile .user-comment-shortcuts-wrap, /* Tastaturkürzel */
                        #your-profile .show-admin-bar.user-admin-bar-front-wrap /* Werkzeugleiste zeigen */
                        {
                            display: none;
                        }
                    </style>
                    <?php
                }
            }
        );
    }


    public static function dashboard_welcome() // Callback zur Darstellung der Meta-Box
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
        <h2>Hallo <?php echo ucwords(wp_get_current_user()->data->display_name); ?></h2>
        <p>
            Schön, dass du da bist! Was nun?
        </p>
        <ul>
            <?php

            foreach (self::$custom_dashboard as $item) {
                ?>
                <li>
                    <div class="icon16 dashicons <?php echo $item['icon']; ?>"></div>
                    <p class="qundg-dashboard-menu-item">
                        <a href="<?php echo $item['url']; ?>"><?php echo $item['text']; ?></a>
                    </p>
                </li>
                <?php
            } ?>
        </ul>
        <?php
    }

    /**
     * Retrieve Customizer class instance.
     *
     * @return \Sloth\Admin\Customizer
     */
    public static function instance()
    {
        if (is_null(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Loads scripts with ansynchrounous tag
     */
    public static function load_scripts_asnychronous()
    {
        add_filter(
            'clean_url',
            function ($url) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
                if ($extension == 'js') {
                    $url .= "' async='async";
                }

                return $url;
            },
            11,
            1
        );
    }

    /**
     * @param $move
     * @param $after
     */
    public static function move_menu_item($move, $after)
    {
        self::$move_menu_items[ $move ] = $after;

        if (! self::done('move_menu_items')) {
            add_action(
                'admin_menu',
                function () {
                    global $menu;
                    $menu = array_values($menu); // sorgt dafür, dass Keys lückenlos nummeriert sind

                    foreach (\Sloth\Admin\Customizer::$move_menu_items as $move => $after) {
                        $to_be_moved = false;

                        // Menüpunkt zwischenspeichern und an alter Stelle entfernen
                        foreach ($menu as $offset => $section) {
                            if ($section[2] == $move) {
                                $to_be_moved = $section;
                                array_splice($menu, $offset, 1);
                                break;
                            }
                        }

                        // Menüpunkt an neuer Stelle einfügen
                        if ($to_be_moved) {
                            foreach ($menu as $offset => $section) {
                                if ($section[2] == $after) {
                                    array_splice($menu, $offset + 1, 0, [$to_be_moved]);
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
     * @param $item
     */
    public static function remove_admin_bar_item($item)
    {
        self::$remove_admin_bar_menus = self::join_array(self::$remove_admin_bar_menus, $item);

        if (! self::done('remove_admin_bar_items')) {
            add_action(
                'wp_before_admin_bar_render',
                function () {
                    global $wp_admin_bar;
                    foreach (\Sloth\Admin\Customizer::$remove_admin_bar_menus as $item) {
                        $wp_admin_bar->remove_menu($item);
                    }
                }
            );
        }
    }

    /**
     * @param $url
     */
    public static function remove_menu_item($url)
    {
        self::$remove_menu_items = self::join_array(self::$remove_menu_items, $url);

        if (! self::done('remove_menu_items')) {
            add_action(
                'admin_menu',
                function () {
                    global $menu;
                    $menu = array_values($menu); // sorgt dafür, dass Keys lückenlos nummeriert sind

                    foreach ($menu as $offset => $section) {
                        if (in_array($section[2], \Sloth\Admin\Customizer::$remove_menu_items)) {
                            unset($menu[ $offset ]);
                        }
                    }
                },
                9999
            );
        }
    }

    /**
     * @param $post_type
     * @param $box
     */
    public static function remove_post_meta_box($post_type, $box)
    {
        if (! isset(self::$remove_meta_boxes[ $post_type ])) {
            self::$remove_meta_boxes[ $post_type ] = [];
        }
        self::$remove_meta_boxes[ $post_type ] = self::join_array(self::$remove_meta_boxes[ $post_type ], $box);

        if (! self::done('remove_post_meta_box')) {
            add_action(
                'admin_head',
                function () {
                    foreach (\Sloth\Admin\Customizer::$remove_meta_boxes as $post_type => $boxes) {
                        foreach ($boxes as $box) {
                            remove_meta_box($box, $post_type, 'normal');
                            remove_meta_box($box, $post_type, 'side');
                        }
                    }
                }
            );
        }
    }

    /**
     * @param $url
     * @param $new_name
     */
    public static function rename_menu_item($url, $new_name)
    {
        self::$rename_menu_items[ $url ] = $new_name;

        if (! self::done('rename_menu_items')) {
            add_action(
                'admin_menu',
                function () {
                    global $menu;

                    foreach ($menu as &$section) {
                        if (array_key_exists($section[2], \Sloth\Admin\Customizer::$rename_menu_items)) {
                            $section[0] = \Sloth\Admin\Customizer::$rename_menu_items[ $section[2] ];
                        }
                    }
                },
                9999996
            );
        }
    }

    /**
     * Render the footer for WordPress admin
     *
     * @return mixed
     */
    public function renderFooter()
    {
        global $wp_version;

        $versions = [
            "WordPress ${wp_version}",
        ];

        if (file_exists(DIR_ROOT . DS . '.version')) {
            $app_version = file_get_contents(DIR_ROOT . DS . '.version');
            $versions[]  = "App ${app_version}";
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
     * @param      $row
     * @param      $button
     * @param bool $position
     */
    public static function tinymce_add_button($row, $button, $position = false)
    {
        self::$tinymce_add_buttons[ $row ][] = [
            'name'     => $button,
            'position' => $position,
        ];
        $filter                              = $row === 1 ? 'mce_buttons' : 'mce_buttons_' . $row;

        if (! self::done('tinymce_add_buttons_' . $filter)) {
            add_filter(
                $filter,
                function ($buttons) {
                    $row = substr(current_filter(), - 1);
                    if (! ctype_digit($row)) {
                        $row = 1;
                    }
                    $add_buttons = \Sloth\Admin\Customizer::$tinymce_add_buttons[ $row ];
                    foreach ($add_buttons as $button) {
                        if ($button['position'] === false) {
                            $buttons[] = $button['name'];
                        } else {
                            array_splice(
                                $buttons,
                                $button['position'],
                                0,
                                $button['name']
                            ); // sonst an gewünschter Position ins Array schieben
                        }
                    }

                    return $buttons;
                }
            );
        }
    }

    /**
     * @param $row
     * @param $button
     */
    public static function tinymce_remove_button($row, $button)
    {
        self::$tinymce_remove_buttons[ $row ] = self::join_array(self::$tinymce_remove_buttons[ $row ], $button);

        $filter = $row === 1 ? 'mce_buttons' : 'mce_buttons_' . $row;

        if (! self::done('tinymce_remove_buttons_' . $filter)) {
            add_filter(
                $filter,
                function ($buttons) {
                    $row = substr(current_filter(), - 1);
                    if (! ctype_digit($row)) {
                        $row = 1;
                    }
                    $remove_buttons = \Sloth\Admin\Customizer::$tinymce_remove_buttons[ $row ];
                    foreach ($remove_buttons as $button_name) {
                        $key = array_search($button_name, $buttons);
                        if ($key !== false) {
                            unset($buttons[ $key ]);
                        }
                    }

                    return $buttons;
                }
            );
        }
    }

    /**
     * @param $what
     *
     * @return bool
     */
    private static function done($what)
    {
        if (in_array($what, self::$done)) {
            return true;
        } else {
            self::$done[] = $what;

            return false;
        }
    }

    /**
     * @param $existing
     * @param $new
     *
     * @return array
     */
    private static function join_array($existing, $new)
    {
        if (is_array($new)) {
            return array_merge($existing, $new);
        } else {
            $existing[] = $new;

            return $existing;
        }
    }
}
