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
        $this->app->singleton(ModuleRegistrar::class);
    }

    /**
     * Register module hooks.
     *
     * @return array<string, callable|array<callable>>
     * @since 1.0.0
     *
     */
    public function getHooks(): array
    {
        return [
            'init' => fn() => app(ModuleRegistrar::class)->init(),
            'rest_api_init' => fn() => app(ModuleRegistrar::class)->registerJsonEndpoints()
        ];
    }
}
