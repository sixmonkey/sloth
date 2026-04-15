<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Facades\Configure;

/**
 * Service provider for navigation menu registration.
 *
 * Registers WordPress navigation menus from theme configuration.
 *
 * ## Configuration
 *
 * Define menus in config('theme.menus'):
 * ```php
 * Configure::write('theme.menus', [
 *     'primary' => 'Primary Navigation',
 *     'footer' => 'Footer Navigation',
 *     'social' => 'Social Links',
 * ]);
 * ```
 *
 * Each key becomes the menu location identifier, and the value is the
 * admin label shown in Appearance > Menus.
 *
 * ## Usage
 *
 * In templates, use wp_nav_menu() with the location:
 * ```twig
 * {{ wp_nav_menu({'theme_location': 'primary'}) }}
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class MenuServiceProvider
{
    /**
     * Register navigation menus from config.
     *
     * Reads menu definitions from config('theme.menus') and registers
     * them with WordPress using register_nav_menu().
     *
     * The config should be an array with location slugs as keys and
     * display names as values.
     *
     * @since 1.0.0
     *
     * @throws \Exception If theme.menus is not an array
     */
    public function register(): void
    {
        if (Configure::read('theme.menus')) {
            if (!is_array(Configure::read('theme.menus'))) {
                throw new \Exception('theme.menus must be an array!');
            }

            foreach (Configure::read('theme.menus') as $location => $name) {
                \register_nav_menu($location, (string) $name);
            }
        }
    }
}
