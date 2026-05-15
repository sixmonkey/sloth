<?php

declare(strict_types=1);
namespace Sloth\Core\Traits;

use Illuminate\Support\Collection;
use Sloth\Core\Manifest\ProvidersManifestBuilder;
use Sloth\Core\Manifest\VendorProviderManifestBuilder;
use Sloth\Core\ServiceProvider;

/**
 * Service provider registration and booting for the Application.
 *
 * Extracted from Application to keep that class focused on lifecycle
 * and container management. This trait provides:
 *
 * - Provider registration boilerplate (registerProviders, register, bootProviders)
 * - WordPress hook/filter wiring (normalizeCallbacks)
 * - Provider querying (getLoadedProviders)
 *
 * @since 2.0.0
 */
trait ApplicationProviderTrait
{
    /**
     * Registry of loaded service providers.
     *
     * @var array<string, ServiceProvider>
     *
     * @since 1.0.0
     */
    protected array $loadedProviders = [];

    // -------------------------------------------------------------------------
    // Providers
    // -------------------------------------------------------------------------

    /**
     * Register all core framework service providers.
     *
     * Order matters — providers listed first are registered first.
     * ConfigureServiceProvider must come before any provider that
     * calls Configure::read/write during registration.
     *
     * After all hardcoded providers are registered, the method discovers
     * additional providers via ProvidersManifestBuilder (scans app/Providers/
     * and theme/Providers/ for classes extending Sloth\Core\ServiceProvider).
     *
     * @since 1.0.0
     */
    protected function registerProviders(): void
    {
        $providers = [
            // Compatibility — must be first so $GLOBALS proxies are available
            \Sloth\Compatibility\LegacyGlobalsServiceProvider::class,

            // Infrastructure
            \Sloth\Event\EventServiceProvider::class,
            \Sloth\Event\WordPressEventBridge::class,
            \Sloth\Filesystem\FilesystemServiceProvider::class,
            \Sloth\Cache\CacheServiceProvider::class,
            \Sloth\Http\RequestContextServiceProvider::class,
            \Sloth\Http\HttpServiceProvider::class,
            \Sloth\Core\ExceptionServiceProvider::class,
            \Sloth\Debug\DebugServiceProvider::class,
            \Sloth\Core\ApplicationServiceProvider::class,

            // Theme — config + view paths before other providers read them
            \Sloth\Theme\ThemeServiceProvider::class,

            // Framework
            \Sloth\Finder\FinderServiceProvider::class,
            \Sloth\View\ViewServiceProvider::class,
            \Sloth\Pagination\PaginationServiceProvider::class,
            \Sloth\Request\RequestServiceProvider::class,
            \Sloth\Validation\ValidationServiceProvider::class,

            // WordPress integration
            \Sloth\Database\DatabaseServiceProvider::class,
            \Sloth\Model\ModelServiceProvider::class,
            \Sloth\Context\ContextServiceProvider::class,
            \Sloth\Template\TemplateServiceProvider::class,
            \Sloth\Routing\RoutingServiceProvider::class,
            \Sloth\Routing\UrlServiceProvider::class,
            \Sloth\Api\ApiServiceProvider::class,
            \Sloth\Media\MediaServiceProvider::class,
            \Sloth\Admin\AdminServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Deployment\DeploymentServiceProvider::class,
            \Sloth\ACF\AcfServiceProvider::class,
            \Sloth\Options\OptionsServiceProvider::class,

            // Console
            \Sloth\Console\ConsoleServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->register($provider);
        }

        // Autodiscover app/Providers/ and theme/Providers/
        $builder = new ProvidersManifestBuilder($this);
        $builder->init();

        foreach ($builder->getEntries() as $provider) {
            $this->register($provider);
        }

        // Autodiscover vendor package providers from installed.json
        $vendorBuilder = new VendorProviderManifestBuilder($this);
        $vendorBuilder->init();

        foreach ($vendorBuilder->getEntries() as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Register a service provider with the application.
     *
     * Instantiates string class names automatically. Skips already-registered
     * providers unless $force is true.
     *
     * @param ServiceProvider|string $provider
     * @param bool                   $force    force re-registration
     *
     * @since 1.0.0
     */
    public function register(string|ServiceProvider $provider, bool $force = false): ServiceProvider
    {
        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        $name = $provider::class;

        if (isset($this->loadedProviders[$name]) && !$force) {
            return $provider;
        }

        $this->instance($name, $provider);
        $provider->register();
        $this->loadedProviders[$name] = $provider;

        return $provider;
    }

    /**
     * Boot all registered providers and register their hooks and filters.
     *
     * Runs in two passes: first boot() on all providers, then registers
     * WordPress hooks/filters. This ensures all bindings are available
     * before any hook callback fires.
     *
     * @since 1.0.0
     */
    protected function bootProviders(): void
    {
        $providers = $this->getLoadedProviders();

        $providers->each(function (ServiceProvider $provider): void {
            $provider->boot();
        });

        $providers->each(function (ServiceProvider $provider): void {
            foreach ($provider->getHooks() as $hook => $value) {
                foreach ($this->normalizeCallbacks($value) as $callback) {
                    add_action($hook, $callback['fn'], $callback['priority'], PHP_INT_MAX);
                }
            }

            foreach ($provider->getFilters() as $filter => $value) {
                foreach ($this->normalizeCallbacks($value) as $callback) {
                    add_filter($filter, $callback['fn'], $callback['priority'], PHP_INT_MAX);
                }
            }
        });
    }

    /**
     * Normalize a hook/filter value into a flat array of callback descriptors.
     *
     * Accepts three forms:
     * - Callable: `fn() => ...`
     * - Array with callback key: `['callback' => fn() => ..., 'priority' => 20]`
     * - Array of either of the above
     *
     * @param  mixed                                          $value
     * @return array<int, array{fn: callable, priority: int}>
     *
     * @since 1.0.0
     */
    private function normalizeCallbacks(mixed $value): array
    {
        if (is_callable($value)) {
            return [['fn' => $value, 'priority' => 10]];
        }

        if (isset($value['callback'])) {
            return [['fn' => $value['callback'], 'priority' => $value['priority'] ?? 10]];
        }

        return array_map(function (mixed $item): array {
            if (is_callable($item)) {
                return ['fn' => $item, 'priority' => 10];
            }

            return ['fn' => $item['callback'], 'priority' => $item['priority'] ?? 10];
        }, $value);
    }

    /**
     * Get all loaded service providers as a Collection.
     *
     * @return Collection<string, ServiceProvider>
     *
     * @since 1.0.0
     */
    public function getLoadedProviders(): Collection
    {
        return collect($this->loadedProviders);
    }
}
