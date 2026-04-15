<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Core\ServiceProvider;
use Sloth\Facades\Configure;

/**
 * Service provider for navigation menu registration.
 *
 * Registers WordPress navigation menus from theme configuration.
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class MenuServiceProvider extends ServiceProvider
{
    /**
     * Register navigation menus hooks.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'init' => fn() => $this->registerNavMenus(),
        ];
    }

    /**
     * Register navigation menus from config.
     *
     * @since 1.0.0
     *
     * @throws \Exception If theme.menus is not an array
     */
    public function registerNavMenus(): void
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
