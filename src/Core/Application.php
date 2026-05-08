<?php

declare(strict_types=1);
namespace Sloth\Core;

use function Illuminate\Filesystem\join_paths;
use Deprecated;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Sloth\Facades\Facade;
use Sloth\Model\Model;
use Sloth\Model\Taxonomy;

/**
 * Application Container.
 *
 * The main application container for the Sloth framework.
 * Extends Laravel's Container to provide dependency injection
 * and service provider registration.
 *
 * ## Responsibilities
 *
 * This class is intentionally lean — it only owns:
 * - Boot lifecycle (configure/boot/isBooted)
 * - Container registration
 * - Path management
 * - Provider registration and booting
 * - Environment helpers
 *
 * Everything else lives in dedicated ServiceProviders:
 * - Database connection → CorcelServiceProvider
 * - Config loading → ConfigServiceProvider
 * - Theme setup → ThemeServiceProvider
 * - Filesystem → FilesystemServiceProvider
 * - Cache → CacheServiceProvider
 *
 * ## Boot lifecycle
 *
 * The application boots exactly once on the `after_setup_theme` hook
 * (priority 0). Subsequent calls to `configure()->boot()` are no-ops.
 *
 * ```php
 * // In sloth.php MU-plugin:
 * add_action('after_setup_theme', function () {
 *     Application::configure()->boot();
 * }, 0);
 * ```
 *
 * @since 1.0.0
 * @see Container
 */
class Application extends Container
{
    /**
     * Application version.
     *
     * @since 1.0.0
     */
    public const version = '1.0.0';

    /**
     * Whether the application has already been booted.
     *
     * @since 1.0.0
     */
    private static bool $booted = false;

    /**
     * Cached base path — set once by guessBasePath().
     *
     * Avoids repeated filesystem walks on multiple calls.
     *
     * @since 1.0.0
     */
    private static ?string $cachedBasePath = null;

    /**
     * Registry of loaded service providers.
     *
     * @since 1.0.0
     *
     * @var array<string, ServiceProvider>
     */
    protected array $loadedProviders = [];

    /**
     * Class aliases registered on boot.
     *
     * @since 1.0.0
     *
     * @var array<string, class-string>
     */
    private array $classAliases = [
        'Cache'      => \Sloth\Facades\Cache::class,
        'File'       => \Sloth\Facades\File::class,
        'View'       => \Sloth\Facades\View::class,
        'Configure'  => \Sloth\Facades\Configure::class,
        'Validator'  => \Sloth\Facades\Validation::class,
        'Deployment' => \Sloth\Facades\Deployment::class,
        'Customizer' => \Sloth\Facades\Customizer::class,
    ];

    public ?string $basePath = null;

    // -------------------------------------------------------------------------
    // Boot lifecycle
    // -------------------------------------------------------------------------
    /**
     * Create and return the application instance.
     *
     * Returns the existing instance if already booted.
     * This is the preferred entry point — chain with ->boot().
     *
     * @since 1.0.0
     */
    public static function configure(): static
    {
        if (self::$booted) {
            return static::getInstance();
        }

        // @phpstan-ignore new.static
        return new static();
    }

    /**
     * Create a new Application instance.
     *
     * @since 1.0.0
     */
    protected function __construct()
    {
        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);
    }

    /**
     * Boot the application.
     *
     * Idempotent — subsequent calls are no-ops.
     *
     * Boot order:
     * 1. Guard — skip if already booted or WordPress not installed
     * 2. Config repository
     * 3. Facades
     * 4. Base paths
     * 5. Providers (register + boot + hooks)
     * 6. Aliases
     *
     * @since 1.0.0
     */
    public function boot(): static
    {
        if (self::$booted || !is_blog_installed()) {
            return $this;
        }

        // Config repository — must exist before any provider reads config
        if (Facade::getFacadeApplication()?->bound('config')) {
            $this->singleton('config', fn () => Facade::getFacadeApplication()->make('config'));
        } else {
            $this->singleton('config', fn (): \Illuminate\Config\Repository => new \Illuminate\Config\Repository([]));
        }

        Facade::setFacadeApplication($this);

        // Paths — must exist before providers boot
        $this->registerBasePaths();

        // Providers
        $this->registerProviders();
        $this->bootProviders();

        // Aliases — after providers so all facades are bound
        $this->setAliases();

        self::$booted = true;

        return $this;
    }

    /**
     * Check whether the application has been booted.
     *
     * @since 1.0.0
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }

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
            \Sloth\Configure\ConfigureServiceProvider::class,
            \Sloth\Event\EventServiceProvider::class,
            \Sloth\Event\WordPressEventBridge::class,
            \Sloth\Filesystem\FilesystemServiceProvider::class,
            \Sloth\Cache\CacheServiceProvider::class,
            \Sloth\Http\RequestContextServiceProvider::class,
            \Sloth\Http\HttpServiceProvider::class,
            ExceptionServiceProvider::class,
            \Sloth\Debug\DebugServiceProvider::class,
            ApplicationServiceProvider::class,

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
            \Sloth\Api\ApiServiceProvider::class,
            \Sloth\Media\MediaServiceProvider::class,
            \Sloth\Admin\AdminServiceProvider::class,
            \Sloth\LayotterBridge\LayotterBridgeServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Deployment\DeploymentServiceProvider::class,
            \Sloth\ACF\AcfServiceProvider::class,

            // Console
            \Sloth\Console\ConsoleServiceProvider::class,
        ];

        // Register framework providers first (including FilesystemServiceProvider)
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        // Autodiscover app/Providers/ and theme/Providers/
        // (app('files') is now available since FilesystemServiceProvider is registered)
        $builder = new Manifest\ProvidersManifestBuilder($this);
        $builder->init();

        foreach ($builder->getEntries() as $provider) {
            $this->register($provider);
        }

        // Autodiscover vendor package providers from installed.json
        // (uses extra.laravel.providers — Laravel-compatible format)
        $vendorBuilder = new Manifest\VendorProviderManifestBuilder($this);
        $vendorBuilder->init();

        foreach ($vendorBuilder->getEntries() as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Register a service provider with the application.
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
     * Normalize callbacks from getHooks/getFilters format.
     *
     * @since 1.0.0
     *
     * @param  mixed                                          $value
     * @return array<int, array{fn: callable, priority: int}>
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

    // -------------------------------------------------------------------------
    // Aliases
    // -------------------------------------------------------------------------

    /**
     * Create class aliases for framework facades.
     *
     * @since 1.0.0
     */
    private function setAliases(): void
    {
        foreach ($this->classAliases as $alias => $class) {
            if (!class_exists($alias)) {
                class_alias($class, $alias);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Paths
    // -------------------------------------------------------------------------

    /**
     * Register all base paths for the application.
     *
     * Called after after_setup_theme — WordPress functions are available.
     *
     * @since 1.0.0
     */
    protected function registerBasePaths(): void
    {
        $this->basePath = $this->guessBasePath();

        $this->addPath('base', $this->basePath);
        $this->addPath('app', $this->basePath . '/app');
        $this->addPath('vendor', $this->basePath . '/vendor');
        $this->addPath('framework', dirname(__DIR__));
        $this->addPath('cms', ABSPATH);
        $this->addPath('plugins', WP_PLUGIN_DIR);
        $this->addPath('theme', get_template_directory());
        $this->addPath('uploads', wp_upload_dir()['basedir']);

        // Cache and logs live in the theme — auto-create if missing
        foreach (['cache', 'logs'] as $key) {
            $path = get_template_directory() . '/' . $key;

            if (!is_dir($path)) {
                mkdir($path, 0o755, true);
            }
            $this->addPath($key, $path);
        }
    }

    /**
     * Guess the project root path.
     *
     * Resolution order:
     * 1. `SLOTH_BASE_PATH` constant — explicit override
     * 2. Walk up from __DIR__ to find composer.json outside vendor/
     * 3. Theme-only fallback — app/ inside get_template_directory()
     *
     * Result is cached statically for the duration of the request.
     *
     * @throws RuntimeException
     *
     * @since 1.0.0
     */
    protected function guessBasePath(): string
    {
        if (self::$cachedBasePath !== null) {
            return self::$cachedBasePath;
        }

        if (defined('SLOTH_BASE_PATH')) {
            return self::$cachedBasePath = rtrim((string) SLOTH_BASE_PATH, '/');
        }

        $dir = dirname(match (defined('ABSPATH')) {
            true    => ABSPATH,
            default => __DIR__
        });

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json') && !str_contains($dir, '/vendor/')) {
                return self::$cachedBasePath = $dir;
            }
            $dir = dirname($dir);
        }

        if (function_exists('get_template_directory')) {
            $theme = get_template_directory();

            if (is_dir($theme . '/app')) {
                return self::$cachedBasePath = $theme;
            }
        }

        throw new RuntimeException(
            'Sloth could not determine the project base path. '
            . 'Define SLOTH_BASE_PATH in wp-config.php if your structure is non-standard.',
        );
    }

    /**
     * Add a path to the container.
     *
     * @param string $key  Path identifier (e.g. 'cache', 'theme').
     * @param string $path full filesystem path
     *
     * @since 1.0.0
     */
    public function addPath(string $key, string $path): void
    {
        if (is_dir($path)) {
            $path = realpath($path);
        }
        $this->instance('path.' . $key, $path);
    }

    /**
     * Get the base path of the Laravel installation.
     *
     * @param string $path
     */
    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    /**
     * Get a path from the container.
     *
     * @param string $path   optional subpath to append
     * @param string $prefix path key (default: 'app')
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @since 1.0.0
     */
    public function path(string $path = '', string $prefix = 'app'): string
    {
        return join_paths($this->get('path.' . $prefix), $path);
    }

    /**
     * Get the config path.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function configPath(): string
    {
        return $this->path('config');
    }

    // -------------------------------------------------------------------------
    // Environment
    // -------------------------------------------------------------------------

    /**
     * Check if running in a local/development environment.
     *
     * @since 1.0.0
     */
    public function isLocal(): bool
    {
        return in_array(env('WP_ENV', 'production'), ['development', 'develop', 'dev'], true);
    }

    /**
     * Check if running in production.
     *
     * @since 1.0.0
     */
    public function isProduction(): bool
    {
        return env('WP_ENV', 'production') === 'production';
    }

    /**
     * Check if running unit tests.
     *
     * @since 1.0.0
     */
    public function runningUnitTests(): bool
    {
        return defined('WP_TESTS_PHASE');
    }

    /**
     * Get the current environment name.
     *
     * @since 1.0.0
     */
    public function environment(): string
    {
        return env('WP_ENV', 'production');
    }

    // -------------------------------------------------------------------------
    // Backwards compatibility
    // -------------------------------------------------------------------------
    /**
     * Get the template context.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    #[Deprecated(message: "use app('context')->getContext() instead")]
    public function getContext(): array
    {
        return $this->bound('context') ? $this['context']->getContext() : [];
    }

    /**
     * Check if running in a development environment.
     *
     * @since 1.0.0
     */
    #[Deprecated(message: 'use app()->isLocal() instead')]
    public function isDevEnv(): bool
    {
        return $this->isLocal();
    }

    /**
     * Get a class for a model by its post_type.
     *
     * @todo deprecate in future versions
     *
     * @param string $key
     *
     * @throws BindingResolutionException
     */
    public function getModelClass(string $key = ''): string
    {
        return app('sloth.models')[$key] ?? Model::class;
    }

    /**
     * Get all registered models.
     *
     * @throws BindingResolutionException
     *
     * @todo deprecate in future versions
     */
    public function getAllModels(): array
    {
        return collect(app('sloth.models'));
    }

    /**
     * Get a class for a taxonomy by its taxonomy type.
     *
     * @todo deprecate in future versions
     *
     * @param string $key
     *
     * @throws BindingResolutionException
     */
    public function getTaxonomyClass(string $key = ''): string
    {
        return app('sloth.taxonomies')[$key] ?? Taxonomy::class;
    }

    /**
     * Get all registered taxonomies.
     *
     * @throws BindingResolutionException
     *
     * @todo deprecate in future versions
     */
    public function getAllTaxonomies(): array
    {
        return app('sloth.taxonomies');
    }
    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    /**
     * Get the application version.
     *
     * @since 1.0.0
     */
    public function version(): string
    {
        return self::version;
    }

    /**
     * Join the given paths together.
     *
     * @param string $basePath
     * @param string $path
     */
    public function joinPaths(string $basePath, string $path = ''): string
    {
        return join_paths($basePath, $path);
    }
}
