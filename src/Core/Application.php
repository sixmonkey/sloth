<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sloth\Console\ConsoleServiceProvider;
use Sloth\Facades\Facade;

use function Illuminate\Filesystem\join_paths;

/**
 * Application Container
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
 * @see \Illuminate\Container\Container
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
     * @var array<string, ServiceProvider>
     */
    protected array $loadedProviders = [];

    /**
     * Class aliases registered on boot.
     *
     * @since 1.0.0
     * @var array<string, class-string>
     */
    private array $classAliases = [
        'Cache' => \Sloth\Facades\Cache::class,
        'File' => \Sloth\Facades\File::class,
        'View' => \Sloth\Facades\View::class,
        'Configure' => \Sloth\Facades\Configure::class,
        'Validator' => \Sloth\Facades\Validation::class,
        'Deployment' => \Sloth\Facades\Deployment::class,
        'Customizer' => \Sloth\Facades\Customizer::class,
    ];

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
        if (static::$booted) {
            return static::getInstance();
        }

        return new static();
    }

    /**
     * Create a new Application instance.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        static::setInstance($this);
        $this->instance('app', $this);
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
        if (static::$booted || !is_blog_installed()) {
            return $this;
        }

        // Config repository — must exist before any provider reads config
        if (Facade::getFacadeApplication()?->bound('config')) {
            $this->singleton('config', fn() => Facade::getFacadeApplication()->make('config'));
        } else {
            $this->singleton('config', fn() => new \Illuminate\Config\Repository([]));
        }

        Facade::setFacadeApplication($this);

        // Paths — must exist before providers boot
        $this->registerBasePaths();

        // Providers
        $this->registerProviders();
        $this->bootProviders();

        // Aliases — after providers so all facades are bound
        $this->setAliases();

        static::$booted = true;

        return $this;
    }

    /**
     * Check whether the application has been booted.
     *
     * @since 1.0.0
     */
    public static function isBooted(): bool
    {
        return static::$booted;
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
     * @since 1.0.0
     */
    protected function registerProviders(): void
    {
        $providers = [
            // Compatibility — must be first so $GLOBALS proxies are available
            \Sloth\Compatibility\LegacyGlobalsServiceProvider::class,

            // Infrastructure
            \Sloth\Configure\ConfigureServiceProvider::class,
            \Sloth\Filesystem\FilesystemServiceProvider::class,
            \Sloth\Cache\CacheServiceProvider::class,
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
            \Sloth\Api\ApiServiceProvider::class,
            \Sloth\Media\MediaServiceProvider::class,
            \Sloth\Admin\AdminServiceProvider::class,
            \Sloth\Layotter\LayotterServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Deployment\DeploymentServiceProvider::class,

            \Sloth\Console\ConsoleServiceProvider::class
        ];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Register a service provider with the application.
     *
     * @param ServiceProvider|string $provider
     * @param bool $force Force re-registration.
     * @return ServiceProvider
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

        $providers->each(fn(ServiceProvider $p) => $p->boot());

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
     * @param mixed $value
     * @return array<int, array{fn: callable, priority: int}>
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

        return array_map(function ($item) {
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
        $base = $this->guessBasePath();

        $this->addPath('base', $base);
        $this->addPath('app', $base . '/app');
        $this->addPath('vendor', $base . '/vendor');
        $this->addPath('framework', dirname(__DIR__));
        $this->addPath('cms', ABSPATH);
        $this->addPath('plugins', WP_PLUGIN_DIR);
        $this->addPath('theme', get_template_directory());

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
     * @throws \RuntimeException
     * @since 1.0.0
     */
    protected function guessBasePath(): string
    {
        if (static::$cachedBasePath !== null) {
            return static::$cachedBasePath;
        }

        if (defined('SLOTH_BASE_PATH')) {
            return static::$cachedBasePath = rtrim(SLOTH_BASE_PATH, '/');
        }

        $dir = __DIR__;
        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json') && !str_contains($dir, '/vendor/')) {
                return static::$cachedBasePath = $dir;
            }
            $dir = dirname($dir);
        }

        if (function_exists('get_template_directory')) {
            $theme = get_template_directory();
            if (is_dir($theme . '/app')) {
                return static::$cachedBasePath = $theme;
            }
        }

        throw new \RuntimeException(
            'Sloth could not determine the project base path. '
            . 'Define SLOTH_BASE_PATH in wp-config.php if your structure is non-standard.'
        );
    }

    /**
     * Add a path to the container.
     *
     * @param string $key Path identifier (e.g. 'cache', 'theme').
     * @param string $path Full filesystem path.
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
     * Get a path from the container.
     *
     * @param string $path Optional subpath to append.
     * @param string $prefix Path key (default: 'app').
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @since 1.0.0
     */
    public function path(string $path = '', string $prefix = 'app'): string
    {
        return join_paths($this->get('path.' . $prefix), $path);
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
     * @since 1.0.0
     * @deprecated Use app('context')->getContext() instead.
     */
    public function getContext(): array
    {
        return $this->bound('context') ? $this['context']->getContext() : [];
    }

    /**
     * Check if running in a development environment.
     *
     * @deprecated Use app()->isLocal() instead.
     * @since 1.0.0
     */
    public function isDevEnv(): bool
    {
        return $this->isLocal();
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
}
