<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Menu as CorcelMenu;

/**
 * Menu Model
 *
 * Extends Corcel's Menu model to provide additional functionality
 * for WordPress navigation menus.
 *
 * @since 1.0.0
 * @see CorcelMenu For the base Corcel implementation
 *
 * @example
 * ```php
 * // Get menu by location
 * $menu = Menu::location('primary');
 *
 * // Get menu items
 * foreach ($menu->items as $item) {
 *     echo $item->title;
 * }
 * ```
 */
class Menu extends CorcelMenu
{
    /**
     * Gets a menu by its WordPress location.
     *
     * @since 1.0.0
     *
     * @param string $location_name The menu location identifier
     *
     * @return Menu|\Illuminate\Database\Eloquent\Builder The menu query builder
     *
     * @uses get_nav_menu_locations() To find the menu ID for the location
     */
    public static function location(string $location_name): Menu|\Illuminate\Database\Eloquent\Builder
    {
        $locations = get_nav_menu_locations();
        $id = null;

        foreach ($locations as $location => $location_id) {
            if ($location === $location_name) {
                $id = $location_id;
                break;
            }
        }

        return parent::where('term_taxonomy_id', $id);
    }

    /**
     * Gets the location name for this menu.
     *
     * @since 1.0.0
     *
     * @return int|string|false The location name or false if not found
     */
    public function getLocationAttribute(): int|string|false
    {
        $locations = get_nav_menu_locations();

        return array_search($this->term_taxonomy_id, $locations, true);
    }

    /**
     * Gets the menu items for this menu.
     *
     * @since 1.0.0
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany The items relationship
     */
    public function items(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            MenuItem::class,
            'term_relationships',
            'term_taxonomy_id',
            'object_id'
        )->orderBy('menu_order');
    }
}
