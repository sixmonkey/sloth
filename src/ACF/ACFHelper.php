<?php


namespace Sloth\ACF;


use Sloth\Field\Image;
use Sloth\Singleton\Singleton;

class ACFHelper extends Singleton {
    public function __construct() {
        add_action( 'init', [ $this, 'addFilters' ] );
    }

    final public function addFilters() {
        if ( \Configure::read( 'layotter_prepare_fields' ) == 2 ) {
            add_filter( 'acf/format_value/type=image', [ $this, 'load_image' ], 10, 3 );
        }
        add_action( 'admin_init', [ $this, 'auto_sync_acf_fields' ] );

    }

    final public function load_image( $value, $post_id, $field ) {
        if ( substr( $field['_name'], 0, 6 ) === '_qundg' ) {
            return $value;
        }

        $id = is_array( $value ) ? (int) $value['ID'] : $value;

        return new Image( $id );
    }

    /**
     * Automagically sync advanced custom fields json fieldgroups during development
     */
    public function auto_sync_acf_fields() {
        $autosync_acf = \Configure::read( 'autosync_acf' );
        if ( ! function_exists( 'acf_get_field_groups' ) ||
             ! $GLOBALS['sloth::plugin']->isDevEnv() ||
             $autosync_acf === false ) {
            {
                return;
            }
        }

        $groups = acf_get_field_groups();

        if ( empty( $groups ) ) {
            return;
        }

        foreach ( $groups as $group ) {
            $local    = acf_maybe_get( $group, 'local', false );
            $modified = acf_maybe_get( $group, 'modified', 0 );
            $private  = acf_maybe_get( $group, 'private', false );

            if ( $private || $local !== 'json' ) {
                continue;
            }

            if ( ( ! $private || $local === 'json' ) &&
                 ( ! $group['ID'] || $modified > get_post_modified_time( 'U', true, $group['ID'], true ) ) ) {
                acf_disable_filters();
                acf_enable_filter( 'local' );
                acf_update_setting( 'json', false );
                $group['fields'] = acf_get_fields( $group );
                $group           = acf_import_field_group( $group );
            }
        }
    }
}
