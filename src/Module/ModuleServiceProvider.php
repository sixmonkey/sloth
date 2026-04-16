<?php

declare(strict_types=1);

namespace Sloth\Module;

use Sloth\Core\ServiceProvider;
use Sloth\Module\Registrars\ModuleRegistrar;

/**
 * Service provider for the Module component.
 *
 * Handles:
 * - Module binding in the container
 * - Module discovery and registration via ModuleRegistrar
 * - Layotter element registration
 * - JSON/AJAX endpoint registration
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module
 * @see \Sloth\Module\Registrars\ModuleRegistrar
 * @see \Sloth\Plugin\Plugin
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register the Module service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->bind(
            'module',
            fn(): Module => new Module()
        );
    }

    /**
     * Register module hooks.
     *
     * @since 1.0.0
     *
     * @return array<string, callable|array<callable>>
     */
    public function getHooks(): array
    {
        $moduleRegister = new ModuleRegistrar();
        return [
            'init' => fn() => $moduleRegister->init(),
        ];
    }
}
