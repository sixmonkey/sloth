<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Menu Facade for accessing the menu model.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Menu
 */
class Menu extends \Sloth\Model\Menu
{
    /**
     * Return the service provider key responsible for the menu class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the menu facade
     */
    protected static function getFacadeAccessor(): string
    {
        return 'menu';
    }
}
