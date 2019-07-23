<?php

namespace Sloth\Admin;

use Sloth\Facades\View;

class Customizer extends \Singleton {

    protected static $instance;

    /**
     * Retrieve Customizer class instance.
     *
     * @return \Sloth\Admin\Customizer
     */
    public static function instance() {
        if ( is_null( static::$instance ) ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Add required hooks to WordPress
     */
    public function boot() {
        // Remove update message from footer
        add_action(
            'admin_menu',
            function () {
                remove_filter( 'update_footer', 'core_update_footer' );
            }
        );

        // set footer text
        add_action(
            'admin_init',
            function () {
                add_filter(
                    'admin_footer_text',
                    [ $this, 'renderFooter' ],
                    999
                );
            }
        );
    }

    public function renderFooter() {
        global $wp_version;

        $versions = [
            "WordPress ${wp_version}",
        ];

        if ( file_exists( DIR_ROOT . DS . '.version' ) ) {
            $app_version = file_get_contents( DIR_ROOT . DS . '.version' );
            $versions[]  = "App ${app_version}";
        }

        $data = [
            'versions' => implode( ' | ', $versions ),
        ];

        $view = View::make( 'Admin.footer' );

        return $view
            ->with( $data )
            ->render();
    }
}
