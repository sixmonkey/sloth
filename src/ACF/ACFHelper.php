<?php


namespace Sloth\ACF;


use Sloth\Field\Image;

class ACFHelper extends \Singleton {
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

    public function auto_sync_acf_fields() {
        $autosync_acf = \Configure::read( 'autosync_acf' );
        if ( ! function_exists( 'acf_get_field_groups' ) ||
             ! $GLOBALS['sloth::plugin']->isDevEnv() ||
             $autosync_acf === false ) {
            {
                return false;
            }
        }

        // vars
        $groups = acf_get_field_groups();
        $sync   = [];

        // bail early if no field groups
        if ( empty( $groups ) ) {
            return;
        }

        // find JSON field groups which have not yet been imported
        foreach ( $groups as $group ) {

            // vars
            $local    = acf_maybe_get( $group, 'local', false );
            $modified = acf_maybe_get( $group, 'modified', 0 );
            $private  = acf_maybe_get( $group, 'private', false );

            // ignore DB / PHP / private field groups
            if ( $local !== 'json' || $private ) {

                // do nothing

            } else if ( ! $group['ID'] ) {

                $sync[ $group['key'] ] = $group;

            } else if ( $modified && $modified > get_post_modified_time( 'U', true, $group['ID'], true ) ) {

                $sync[ $group['key'] ] = $group;
            }
        }

        // bail if no sync needed
        if ( empty( $sync ) ) {
            return;
        }

        if ( ! empty( $sync ) ) { //if( ! empty( $keys ) ) {

            // vars
            $new_ids = [];

            foreach ( $sync as $key => $v ) { //foreach( $keys as $key ) {

                // append fields
                if ( acf_have_local_fields( $key ) ) {

                    $sync[ $key ]['fields'] = acf_get_local_fields( $key );

                }
                // import
                $field_group = acf_import_field_group( $sync[ $key ] );
            }
        }
    }
}
