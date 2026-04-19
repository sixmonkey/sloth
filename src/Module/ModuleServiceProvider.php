<?php

declare(strict_types=1);

namespace Sloth\Module;

use Sloth\Core\ServiceProvider;
use Sloth\Module\Factory\ModuleFactory;
use Sloth\Module\Registrars\ModuleRegistrar;

/**
 * Service provider for the Module component.
 *
 * @since 1.0.0
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        // ModuleFactory — resolves and instantiates theme modules.
        // Bound as singleton since the factory itself is stateless.
        $this->app->singleton('module.factory', ModuleFactory::class);

        // Legacy binding — kept for backwards compatibility.
        // Use app('module.factory')->make() or the module() helper instead.
        $this->app->bind('module', fn(): Module => new Module());

        $this->app->singleton(ModuleRegistrar::class);
    }

    /**
     * Register module hooks.
     *
     * @return array<string, callable|array<callable>>
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'init' => fn() => app(ModuleRegistrar::class)->init(),
            'rest_api_init' => fn() => app(ModuleRegistrar::class)->registerJsonEndpoints(),
        ];
    }
}
