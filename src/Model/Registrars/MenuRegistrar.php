<?php

declare(strict_types=1);

namespace Sloth\Model\Registrars;

use Sloth\Core\Application;
use Sloth\Facades\Configure;

/**
 * Service provider for navigation menu registration.
 *
 * Registers WordPress navigation menus from theme configuration.
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class MenuRegistrar
{
    /**
     * Constructor.
     *
     * @param Application $app The application container instance.
     * @since 1.0.0
     *
     */
    public function __construct(private Application $app) {}

    /**
     * Register navigation menus from config.
     *
     * @throws \Exception If theme.menus is not an array
     * @since 1.0.0
     *
     */
    public function init(): void
    {
        $menus = [];
        foreach (config('theme.menus', []) as $location => $name) {
            $menus[$location] = $name;
            \register_nav_menu($location, (string) $name);
        }
        $this->app->instance('sloth.menus', $menus);
    }
}
