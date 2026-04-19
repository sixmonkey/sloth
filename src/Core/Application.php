<?php

declare(strict_types=1);

namespace Sloth\Core;

use Corcel\Database;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\CircularDependencyException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sloth\Facades\Configure;
use Sloth\Facades\Facade;

use function Illuminate\Filesystem\join_paths;

/**
 * Application Container
 *
 * This is the main application container for the Sloth framework.
 * It extends Laravel's Container to provide dependency injection,
 * service provider registration, and theme bootstrapping.
 *
 * ## Boot lifecycle
 *
 * The application boots once on the `after_setup_theme` hook (priority 0).
 * Subsequent calls to `configure()->boot()` are no-ops — the application
 * tracks its own boot state via `$booted`.
 *
 * ## Usage
 *
 * In the Sloth MU-plugin (sloth.php):
 *
 * ```php
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
     * Prevents double-booting when both the MU-plugin and a theme
     * call configure()->boot().
     *
     * @since 1.0.0
     */
    private static bool $booted = false;

    /**
     * Cached base path — set once by guessBasePath().
     *
     * @since 1.0.0
     */
    private static ?string $cachedBasePath = null;

    /**
     * Project paths mapped by key.
     * Stored in the container as 'path.{key}'.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $paths = [];

    /**
     * Registry of loaded service providers.
     * Maps provider class name to provider instance.
     *
     * @since 1.0.0
     * @var array<string, ServiceProvider>
     */
    protected array $loadedProviders = [];

    /**
     * Class aliases for convenient access to facades.
     *
     * These shortcuts allow classes to be referenced by their short name
     * instead of their fully qualified namespace.
     *
     * @since 1.0.0
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

    /**
     * Current theme path.
     *
     * @since 1.0.0
     */
    public ?string $current_theme_path = null;

    // -------------------------------------------------------------------------
    // Boot lifecycle
    // -------------------------------------------------------------------------

    /**
     * Create and return the application instance.
     *
     * If the application is already booted, returns the existing instance.
     * This is the preferred entry point — call configure()->boot() to start.
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
     * Creates a new Application instance.
     *
     * Registers the application in the container and sets up the
     * legacy $GLOBALS deprecation proxies.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->registerApplication();
    }

    /**
     * Boot the application.
     *
     * Idempotent — subsequent calls are no-ops. On first call:
     * 1. Load development config
     * 2. Set up config repository
     * 3. Register base paths
     * 4. Register all service providers
     * 5. Set class aliases
     * 6. Connect Corcel to WordPress database
     * 7. Set up theme paths and view locations
     * 8. Boot all providers and register hooks/filters
     *
     * @since 1.0.0
     */
    public function boot(): static
    {
        if (static::$booted) {
            return $this;
        }

        if (!is_blog_installed()) {
            return $this;
        }

        @include(get_template_directory() . '/develop.config.php');

        // Config repository
        if (Facade::getFacadeApplication() !== null && Facade::getFacadeApplication()->bound('config')) {
            $existingConfig = Facade::getFacadeApplication()->make('config');
            $this->singleton('config', fn() => $existingConfig);
        } else {
            $this->singleton('config', fn() => new \Illuminate\Config\Repository([]));
        }

        Facade::setFacadeApplication($this);

        // Paths
        $this->registerBasePaths();

        // Configure must be first — everything below may call Configure::write/read
        $this->register(\Sloth\Configure\ConfigureServiceProvider::class);

        // Load framework config files (app/config/*.php)
        $this->loadConfigFiles();

        // Load theme config — registers theme.twig.filters etc. before ViewServiceProvider
        $this->loadThemeConfig();

        // Remaining providers
        $this->registerProviders();
        $this->setAliases();

        // Database
        $this->connectCorcel();

        // Set up theme view paths AFTER providers — view.finder is now available.
        $this->setupThemeViews();

        // Boot providers + register hooks
        $this->bootProviders();

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
    // Application registration
    // -------------------------------------------------------------------------

    /**
     * Registers the Application instance in the container and sets up
     * legacy $GLOBALS deprecation proxies.
     *
     * @since 1.0.0
     */
    public function registerApplication(): void
    {
        static::setInstance($this);
        $this->instance('app', $this);
    }

    // -------------------------------------------------------------------------
    // Paths
    // -------------------------------------------------------------------------

    /**
     * Register all base paths for the application.
     *
     * Paths are derived from WordPress constants and functions.
     * Called after after_setup_theme so get_template_directory() is available.
     *
     * @since 1.0.0
     */
    protected function registerBasePaths(): void
    {
        $base = $this->guessBasePath();

        $this->addPath('base',      $base);
        $this->addPath('app',       $base . '/app');
        $this->addPath('vendor',    $base . '/vendor');
        $this->addPath('cms',       ABSPATH);
        $this->addPath('plugins',   WP_PLUGIN_DIR);
        $this->addPath('theme',     get_template_directory());
        $this->addPath('framework', dirname(__DIR__));

        // Cache and logs live in the theme directory — auto-create if missing
        foreach (['cache', 'logs'] as $key) {
            $path = get_template_directory() . '/' . $key;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            $this->addPath($key, $path);
        }
    }

    /**
     * Guess the project root by walking up from this file until
     * a composer.json outside of vendor/ is found.
     *
     * @since 1.0.0
     * @throws \RuntimeException If the base path cannot be determined.
     */
    protected function guessBasePath(): string
    {
        // Cached statically — may be called multiple times during boot
        if (static::$cachedBasePath !== null) {
            return static::$cachedBasePath;
        }

        // 1. Explicit override — for non-standard project structures
        if (defined('SLOTH_BASE_PATH')) {
            return static::$cachedBasePath = rtrim(SLOTH_BASE_PATH, '/');
        }

        // 2. Walk up from this file looking for composer.json outside vendor/
        $dir = __DIR__;
        while ($dir !== '/') {
            if (
                file_exists($dir . '/composer.json')
                && !str_contains($dir, '/vendor/')
            ) {
                return static::$cachedBasePath = $dir;
            }
            $dir = dirname($dir);
        }

        // 3. Theme-only fallback — app/ lives inside the theme directory
        if (function_exists('get_template_directory')) {
            $themePath = get_template_directory();
            if (is_dir($themePath . '/app')) {
                return static::$cachedBasePath = $themePath;
            }
        }

        throw new \RuntimeException(
            'Sloth could not determine the project base path. ' .
            'Define SLOTH_BASE_PATH in wp-config.php if your structure is non-standard.'
        );
    }

    // -------------------------------------------------------------------------
    // Providers
    // -------------------------------------------------------------------------

    /**
     * Registers all core framework service providers.
     *
     * @since 1.0.0
     */
    protected function registerProviders(): void
    {
        $providers = [
            \Sloth\Compatibility\LegacyGlobalsServiceProvider::class,
            \Sloth\Filesystem\FilesystemServiceProvider::class,
            \Sloth\Cache\CacheServiceProvider::class,
            \Sloth\Debug\DebugServiceProvider::class,
            \Sloth\Finder\FinderServiceProvider::class,
            \Sloth\View\ViewServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Pagination\PaginationServiceProvider::class,
            \Sloth\Layotter\LayotterServiceProvider::class,
            \Sloth\Request\RequestServiceProvider::class,
            \Sloth\Validation\ValidationServiceProvider::class,
            \Sloth\Deployment\DeploymentServiceProvider::class,
            \Sloth\Admin\AdminServiceProvider::class,
            \Sloth\Context\ContextServiceProvider::class,
            \Sloth\Model\ModelServiceProvider::class,
            \Sloth\Api\ApiServiceProvider::class,
            \Sloth\Media\MediaServiceProvider::class,
            \Sloth\Template\TemplateServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Registers a service provider with the application.
     *
     * @param ServiceProvider|string $provider The service provider instance or class name.
     * @param bool                   $force    Force re-registration even if already loaded.
     * @return ServiceProvider The registered service provider.
     * @since 1.0.0
     */
    public function register(string|ServiceProvider $provider, bool $force = false): ServiceProvider
    {
        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        $providerName = $provider::class;

        if (array_key_exists($providerName, $this->loadedProviders) && !$force) {
            return $provider;
        }

        $this->instance($providerName, $provider);
        $provider->register();
        $this->loadedProviders[$providerName] = $provider;

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

    // -------------------------------------------------------------------------
    // Aliases
    // -------------------------------------------------------------------------

    /**
     * Create class aliases for commonly used framework classes.
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
    // Theme setup
    // -------------------------------------------------------------------------

    /**
     * Load theme configuration files before providers register.
     *
     * Loads app.config.php and theme config.php so that Configure::write()
     * values (e.g. theme.twig.filters) are available when providers like
     * ViewServiceProvider register their services.
     *
     * @since 1.0.0
     */
    protected function loadThemeConfig(): void
    {
        $this->current_theme_path = realpath(get_template_directory());

        $themeConfig = $this->current_theme_path . '/config.php';
        if (file_exists($themeConfig)) {
            include_once $themeConfig;
        }

        Configure::write('layotter_prepare_fields', 2);
    }

    /**
     * Set up theme view paths and Twig loader after providers are registered.
     *
     * Must run after ViewServiceProvider — requires view.finder and twig.loader
     * to be bound in the container.
     *
     * @since 1.0.0
     */
    protected function setupThemeViews(): void
    {
        if (is_dir($this->current_theme_path . '/View')) {
            $this['view.finder']->addLocation($this->current_theme_path . '/View');
        }

        $this['view.finder']->addLocation($this->path('_view', 'framework'));
        $this['twig.loader']->setPaths($this['view.finder']->getPaths());
    }

    // -------------------------------------------------------------------------
    // Database
    // -------------------------------------------------------------------------

    /**
     * Establish a database connection for Corcel.
     *
     * Corcel is used to access WordPress data as Eloquent models.
     * Connection parameters are read from WordPress constants.
     *
     * @since 1.0.0
     * @uses DB_HOST
     * @uses DB_NAME
     * @uses DB_USER
     * @uses DB_PASSWORD
     * @uses DB_PREFIX
     */
    private function connectCorcel(): void
    {
        Database::connect([
            'host'     => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix'   => DB_PREFIX,
        ]);

        Model::setEventDispatcher(new Dispatcher($this));
        \Corcel\Model\Post::resolveConnection()->enableQueryLog();
    }

    // -------------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------------

    /**
     * Load configuration files from app/config/ into the config repository.
     *
     * Each PHP file in the config directory becomes a config key.
     * For example, app/config/theme.php becomes config('theme').
     *
     * @since 1.0.0
     */
    private function loadConfigFiles(): void
    {
        $configPath = $this->guessBasePath() . '/app/config/';

        if (!is_dir($configPath)) {
            return;
        }

        // Load app.config.php first — it may register Configure::write() values
        // (e.g. theme.twig.filters) that providers read during registration.
        $appConfig = $configPath . 'app.config.php';
        if (file_exists($appConfig)) {
            require_once $appConfig;
        }

        // Load remaining config files into the Laravel config repository.
        // Each file becomes a config key: theme.php → config('theme').
        foreach (glob($configPath . '*.php') as $file) {
            if (realpath($file) === realpath($appConfig)) {
                continue; // already loaded above
            }
            $key = basename($file, '.php');
            $this['config']->set($key, require_once $file);
        }
    }

    // -------------------------------------------------------------------------
    // Backwards compatibility
    // -------------------------------------------------------------------------

    /**
     * Get the template context.
     *
     * Kept for backwards compatibility with themes that call
     * $GLOBALS['sloth::plugin']->getContext().
     *
     * @deprecated Use app('context')->getContext() instead.
     * @since 1.0.0
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        if ($this->bound('context')) {
            return $this['context']->getContext();
        }
        return [];
    }

    /**
     * Check if this is a development environment.
     *
     * @deprecated Use app()->isLocal() instead.
     * @since 1.0.0
     */
    public function isDevEnv(): bool
    {
        return $this->isLocal();
    }

    // -------------------------------------------------------------------------
    // Environment
    // -------------------------------------------------------------------------

    /**
     * Check if the application is running in a local/development environment.
     *
     * @since 1.0.0
     */
    public function isLocal(): bool
    {
        return in_array(env('WP_ENV', 'production'), ['development', 'develop', 'dev'], true);
    }

    /**
     * Check if the application is running in production.
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
    // Path helpers
    // -------------------------------------------------------------------------

    /**
     * Add a file path to the container.
     *
     * @param string $key  The path identifier (e.g. 'cache', 'theme').
     * @param string $path The full filesystem path.
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
     * Get a file path from the container.
     *
     * @param string $key    The path identifier.
     * @param string $prefix Prefix (default: 'app').
     * @since 1.0.0
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function path(string $path = '', string $prefix = 'app'): string
    {
        return join_paths($this->get('path.' . $prefix), $path);
    }

    // -------------------------------------------------------------------------
    // Providers registry
    // -------------------------------------------------------------------------

    /**
     * Get all loaded service providers as a Collection.
     *
     * @since 1.0.0
     * @return Collection<string, ServiceProvider>
     */
    public function getLoadedProviders(): Collection
    {
        return collect($this->loadedProviders);
    }

    // -------------------------------------------------------------------------
    // Module helper
    // -------------------------------------------------------------------------

    /**
     * Instantiate and render a theme module.
     *
     * @param string               $name    Module name (kebab-case or snake_case).
     * @param array<string, mixed> $data    Key-value pairs to set on the module.
     * @param array<string, mixed> $options Module configuration options.
     * @return string The rendered module output.
     * @throws \Exception If the module class does not exist.
     * @since 1.0.0
     */
    public function callModule(string $name, array $data = [], array $options = []): string
    {
        $moduleName = 'Theme\\Module\\' . Str::camel(str_replace('-', '_', $name)) . 'Module';
        $myModule   = new $moduleName($options);

        foreach ($data as $k => $v) {
            $myModule->set($k, $v);
        }

        return $myModule->render();
    }

    // -------------------------------------------------------------------------
    // Misc
    // -------------------------------------------------------------------------

    /**
     * Resolve a type from the container with custom handling.
     *
     * @param class-string|object  $abstract   The class or interface to resolve.
     * @param array<string, mixed> $parameters Constructor parameters.
     * @return mixed The resolved instance.
     * @throws BindingResolutionException
     * @since 1.0.0
     */
    public function resolveFromContainer($abstract, array $parameters = []): mixed
    {
        if ($abstract === \Illuminate\Pagination\LengthAwarePaginator::class) {
            $abstract = \Sloth\Pagination\Paginator::class;
        }

        return $this->make($abstract, $parameters);
    }

    /**
     * Get the current application version.
     *
     * @since 1.0.0
     */
    public function version(): string
    {
        return self::version;
    }
}
