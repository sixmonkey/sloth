<?php

declare(strict_types=1);
namespace Sloth\View;

use Illuminate\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Override;
use Sloth\Core\ServiceProvider;
use Sloth\View\Engines\Twig\TwigAdapter;
use Sloth\View\Extensions\AbstractViewExtension;
use Sloth\View\Extensions\Manifest\ViewExtensionManifestBuilder;
use Sloth\View\Extensions\SlothViewExtension;

/**
 * Service provider for the View rendering component.
 *
 * Orchestrates view engine adapters and discovers view extensions.
 * Engine-agnostic — knows nothing about Twig or Blade directly.
 *
 * ## Architecture
 *
 * - ViewServiceProvider discovers extensions and builds _helpers/_directives/_shared
 * - Each adapter (TwigAdapter, BladeAdapter) consumes these from the View Factory
 * - Extensions define getHelpers(), getDirectives(), share() — engine-agnostically
 *
 * ## Adding a new engine
 *
 * Add an adapter to $adapters — it will be registered and booted automatically:
 *
 * ```php
 * protected array $adapters = [
 *     TwigAdapter::class,
 *     BladeAdapter::class,
 * ];
 * ```
 *
 * @since 1.0.0
 */
class ViewServiceProvider extends ServiceProvider
{
    /**
     * Registered view adapters.
     *
     * Each adapter handles a specific template engine.
     * All adapters are always registered — the Framework decides, not the developer.
     *
     * @var list<class-string<Extensions\ViewAdapterInterface>>
     *
     * @since 1.0.0
     */
    protected array $adapters = [
        TwigAdapter::class,
        // BladeAdapter::class, // coming soon
    ];

    /**
     * Register View services and all engine adapters.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->registerEngineResolver();
        $this->registerViewFactory();

        // Register all adapters
        collect($this->adapters)
            ->each(fn (string $adapterClass) => new $adapterClass()->register($this->app))
        ;
    }

    /**
     * Boot View services.
     *
     * Discovers all view extensions, collects helpers/directives/shared variables
     * with conflict detection, then boots all adapters.
     *
     * @since 1.0.0
     */
    #[Override]
    public function boot(): void
    {
        $helpers = [];
        $directives = [];
        $registered = ['helpers' => [], 'directives' => []];

        // Discover all extensions — framework built-ins first, then theme extensions
        $extensions = $this->discoverExtensions();

        foreach ($extensions as $extensionClass) {
            $instance = new $extensionClass();

            // Process helpers
            foreach ($this->normalizeEntries($instance->getHelpers()) as $name => $callable) {
                if (isset($registered['helpers'][$name]) && $this->app->isLocal()) {
                    trigger_error(
                        sprintf(
                            'View helper "%s" registered by "%s" was already registered by "%s" and will be overwritten.',
                            $name,
                            $extensionClass,
                            $registered['helpers'][$name],
                        ),
                        E_USER_WARNING,
                    );
                }

                $registered['helpers'][$name] = $extensionClass;
                $helpers[$name] = $callable;
            }

            // Process directives
            foreach ($this->normalizeEntries($instance->getDirectives()) as $name => $callable) {
                if (isset($registered['directives'][$name]) && $this->app->isLocal()) {
                    trigger_error(
                        sprintf(
                            'View directive "%s" registered by "%s" was already registered by "%s" and will be overwritten.',
                            $name,
                            $extensionClass,
                            $registered['directives'][$name],
                        ),
                        E_USER_WARNING,
                    );
                }

                $registered['directives'][$name] = $extensionClass;
                $directives[$name] = $callable;
            }

            // Process shared variables
            foreach ($instance->share() as $key => $value) {
                $this->app['view']->share($key, $value);
            }
        }

        // Store helpers and directives on the View Factory for adapters to consume
        $this->app['view']->share('_helpers', $helpers);
        $this->app['view']->share('_directives', $directives);

        // Boot all adapters
        collect($this->adapters)
            ->each(fn (string $adapterClass) => new $adapterClass()->boot(
                $this->app['view'],
                $this->app,
            ))
        ;
    }

    /**
     * Discover all view extensions.
     *
     * SlothViewExtension always comes first so theme extensions can override
     * framework-provided helpers and directives.
     *
     * @return list<class-string<AbstractViewExtension>>
     *
     * @since 1.0.0
     */
    protected function discoverExtensions(): array
    {
        // SlothViewExtension always first — framework built-ins
        $builtin = [SlothViewExtension::class];

        // Theme extensions discovered from app/Extensions/View/ and theme/Extensions/View/
        $discovered = array_values(array_filter(
            array_keys(new ViewExtensionManifestBuilder($this->app)->getEntries()),
            fn (string $c): bool => $c !== SlothViewExtension::class,
        ));

        return [...$builtin, ...$discovered];
    }

    /**
     * Normalize an entries array to always be [name => callable].
     *
     * Supports:
     * - 'name'             → ['name' => 'name']
     * - 'alias' => 'func'  → ['alias' => 'func']
     * - 'name' => fn()...  → ['name' => fn()...]
     *
     * @param  array<int|string, callable|string> $entries
     * @return array<string, callable|string>
     *
     * @since 1.0.0
     */
    protected function normalizeEntries(array $entries): array
    {
        $normalized = [];

        foreach ($entries as $key => $value) {
            if (is_int($key)) {
                // 'name' → callable 'name'
                $normalized[(string) $value] = $value;
            } else {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Register the EngineResolver.
     *
     * @since 1.0.0
     */
    protected function registerEngineResolver(): void
    {
        $this->app->singleton(
            'view.engine.resolver',
            fn (): EngineResolver => new EngineResolver(),
        );
    }

    /**
     * Register the ViewFinder and View Factory.
     *
     * @since 1.0.0
     */
    protected function registerViewFactory(): void
    {
        $this->app->singleton(
            'view.finder',
            fn ($c): ViewFinder => new ViewFinder($c['files'], [], []),
        );

        $this->app->singleton(
            'view',
            fn ($c): Factory => $this->createViewFactory($c),
        );
    }

    /**
     * Create and configure the View Factory.
     *
     * @since 1.0.0
     *
     * @param mixed $container
     */
    protected function createViewFactory(mixed $container): Factory
    {
        $factory = new Factory(
            $container['view.engine.resolver'],
            $container['view.finder'],
            new Dispatcher($container),
        );

        $factory->setContainer($container);

        return $factory;
    }
}
