<?php
/**
 * User: Kremer
 * Date: 02.01.18
 * Time: 11:44
 */

namespace Sloth\Model;

use Corcel\Model\Menu as CorcelMenu;


class Menu extends CorcelMenu {
    /**
     * @param $location_name
     *
     * @return mixed
     */
    public static function location( $location_name ) {

        $locations = get_nav_menu_locations();

        $id = null;

        foreach ( $locations as $location => $location_id ) {
            if ( $location === $location_name ) {
                $id = $location_id;
                break;
            }
        }

        $menu = parent::where( 'term_taxonomy_id', $id );

        return $menu;
    }

    /**
     * @return false|int|string
     */
    public function getLocationAttribute() {
        $locations = get_nav_menu_locations();

        return array_search( $this->term_taxonomy_id, $locations );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function items() {
        return $this->belongsToMany(
            MenuItem::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id'
        )->orderBy( 'menu_order' );
    }
}
