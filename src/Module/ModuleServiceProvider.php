<?php

declare(strict_types=1);

namespace Sloth\Module;

use Sloth\Core\ServiceProvider;
use Sloth\Module\Factory\ModuleFactory;
use Sloth\Module\Manifest\ModuleManifestBuilder;
use Sloth\Module\Registrar\ModuleRegistrar;

/**
 * Service provider for the Module component.
 *
 * Coordinates the lifecycle of Sloth modules: discovery, JSON/AJAX endpoint
 * registration, and module resolution via ModuleFactory.
 *
 * ## Discovery
 *
 * ModuleManifestBuilder scans app/Module/ and theme/Module/ for classes
 * extending Sloth\Module\Module. Modules with `$json` get AJAX and REST
 * route registration via ModuleRegistrar.
 *
 * Layotter integration is handled separately by LayotterBridgeServiceProvider
 * via LayotterElementRegistrar.
 *
 * ## Hook execution order
 *
 * 1. `init` → ModuleManifestBuilder::init() (discovery + manifest loading)
 * 2. `rest_api_init` → ModuleRegistrar::registerJsonEndpoints()
 *
 * ## Container bindings
 *
 * - **module.factory**: ModuleFactory singleton for resolving and
 *   instantiating theme modules by name.
 * - **module**: Legacy binding for direct Module instantiation. Use
 *   app('module.factory')->make() or the module() helper instead.
 * - **sloth.modules**: List of discovered module class names (FQCNs).
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module                           For the module base class
 * @see \Sloth\Module\Factory\ModuleFactory            For module resolution
 * @see \Sloth\Module\Manifest\ModuleManifestBuilder   For module discovery
 * @see \Sloth\Module\Registrar\ModuleRegistrar         For JSON endpoint registration
 */
class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register module services.
     *
     * Binds the following singletons:
     * - **module.factory**: ModuleFactory for module resolution.
     * - **ModuleManifestBuilder**: Discovers modules and generates the manifest.
     * - **ModuleRegistrar**: Registers JSON/AJAX endpoints for eligible modules.
     *
     * Also registers the legacy `module` binding for direct instantiation.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton('module.factory', ModuleFactory::class);

        $this->app->bind('module', fn(): Module => new Module());

        $this->app->singleton(
            ModuleManifestBuilder::class,
            fn($app) => new ModuleManifestBuilder($app)
        );

        $this->app->singleton(
            ModuleRegistrar::class,
            fn($app) => new ModuleRegistrar(app(ModuleManifestBuilder::class))
        );
    }

    /**
     * Register WordPress action hooks for module management.
     *
     * Returns an array of hook => callback mappings:
     * - **init**: Runs ModuleManifestBuilder::init() for discovery and
     *   binds sloth.modules to the container.
     * - **rest_api_init**: Calls ModuleRegistrar::registerJsonEndpoints()
     *   for modules with $json enabled.
     *
     * @return array<string, callable|array<callable>> Hook mappings.
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'init' => fn() => $this->initModules(),
            'rest_api_init' => fn() => app(ModuleRegistrar::class)->registerJsonEndpoints(),
        ];
    }

    /**
     * Initialize modules: discover, load manifest, and bind to container.
     *
     * Calls ModuleManifestBuilder::init() which discovers Module subclasses,
     * generates Layotter class definitions in the manifest, computes entry
     * data, and writes/loads the manifest.
     *
     * The discovered module class names are bound to the container as
     * `sloth.modules` (array of FQCNs) for use by other services.
     *
     * @since 1.0.0
     */
    protected function initModules(): void
    {
        $builder = app(ModuleManifestBuilder::class);
        $builder->init();

        $this->app->instance('sloth.modules', array_keys($builder->getEntries()));
    }
}
