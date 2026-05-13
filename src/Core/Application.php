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
 * - Path and URI management
 * - Provider registration and booting
 * - Environment helpers
 *
 * Everything else lives in dedicated ServiceProviders:
 * - Database connection → DatabaseServiceProvider
 * - Config loading → ConfigureServiceProvider
 * - Theme setup → ThemeServiceProvider
 * - Filesystem → FilesystemServiceProvider
 * - Cache → CacheServiceProvider
 * - Routing → RoutingServiceProvider
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
 * ## Path and URI management
 *
 * Filesystem paths are stored under the `path.*` prefix:
 *
 * ```php
 * app()->path('theme')           // get_template_directory()
 * app()->path('config', 'app')   // app/config/
 * app()->path('cache')           // theme/cache/
 * ```
 *
 * URIs are stored separately under the `uri.*` prefix:
 *
 * ```php
 * app()->uri('theme')            // get_template_directory_uri()
 * app()->uri('home')             // home_url('/')
 * app()->uri('css/app.css', 'theme') // theme_uri/css/app.css
 * ```
 *
 * @since 1.0.0
 * @see Container
 */
class Application extends Container
{
    // -------------------------------------------------------------------------
    // Constants & static state
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Instance state
    // -------------------------------------------------------------------------

    /**
     * Registry of loaded service providers.
     *
     * @var array<string, ServiceProvider>
     *
     * @since 1.0.0
     */
    protected array $loadedProviders = [];

    /**
     * Class aliases registered on boot.
     *
     * Maps short alias names to fully-qualified Facade class names.
     * Aliases are registered after all providers are booted.
     *
     * @var array<string, class-string>
     *
     * @since 1.0.0
     */
    private array $classAliases = [
        'Cache'      => \Sloth\Facades\Cache::class,
        'Configure'  => \Sloth\Facades\Configure::class,
        'Customizer' => \Sloth\Facades\Customizer::class,
        'Deployment' => \Sloth\Facades\Deployment::class,
        'File'       => \Sloth\Facades\File::class,
        'Validator'  => \Sloth\Facades\Validation::class,
        'View'       => \Sloth\Facades\View::class,
        'URL'        => \Sloth\Facades\URL::class,
        'Options'    => \Sloth\Facades\Options::class,
    ];

    /**
     * The resolved base path of the project.
     *
     * @since 1.0.0
     */
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
     * ```php
     * Application::configure()->boot();
     * ```
     *
     * @since 1.0.0
     */
    /**
     * Create and return the application instance.
     *
     * Returns the existing instance if already booted.
     * This is the preferred entry point — chain with ->boot().
     *
     * ```php
     * // Auto-detect base path
     * Application::configure()->boot();
     *
     * // Explicit base path — skips path guessing (recommended in starters)
     * Application::configure(basePath: __DIR__)->boot();
     * ```
     *
     * @param string|null $basePath explicit project root — if null, guessed automatically
     *
     * @since 1.0.0
     */
    public static function configure(?string $basePath = null): static
    {
        if (self::$booted) {
            return static::getInstance();
        }

        // @phpstan-ignore new.static
        return new static($basePath);
    }

    /**
     * Create a new Application instance.
     *
     * Protected to enforce use of configure() as the entry point.
     * Registers the instance in the container under 'app' and class names.
     *
     * Environment variables are loaded here — before boot() — so that
     * all service providers can safely call env() during registration.
     *
     * @param string|null $basePath explicit project root — if null, guessed via guessBasePath()
     *
     * @since 1.0.0
     */
    protected function __construct(?string $basePath = null)
    {
        // Cache an explicit basePath immediately — skips guessBasePath() entirely
        if ($basePath !== null) {
            self::$cachedBasePath = rtrim($basePath, '/');
        }

        static::setInstance($this);
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);

        // Load .env before providers boot so env() is available everywhere.
        // basePath is guessed here and cached — no performance penalty on
        // the second call in registerBasePaths().
        $this->loadEnvironment();
    }

    /**
     * Load environment variables from .env if present.
     *
     * Silently skips if no .env file exists — this is intentional.
     * The developer is responsible for ensuring the required variables
     * are set through other means (e.g. server environment, wp-config.php).
     *
     * No variables are marked as required here — that is too opinionated
     * for a framework. Validate required variables in your own bootstrap
     * if needed.
     *
     * @since 1.0.0
     */
    private function loadEnvironment(): void
    {
        $basePath = $this->guessBasePath();

        if (file_exists($basePath . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->load();
        }
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
     * 4. Filesystem paths (path.*)
     * 5. Base URIs (uri.*)
     * 6. Providers (register + boot + hooks)
     * 7. Class aliases
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

        // Paths and URIs — must exist before providers boot
        $this->registerBasePaths();
        $this->registerBaseUris();

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
            \Sloth\Options\OptionsServiceProvider::class,

            // Console
            \Sloth\Console\ConsoleServiceProvider::class,
        ];

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

    // -------------------------------------------------------------------------
    // Aliases
    // -------------------------------------------------------------------------

    /**
     * Create class aliases for framework facades.
     *
     * Allows using short names like `Cache::get()` instead of
     * `Sloth\Facades\Cache::get()` in theme code.
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
     * Register all base filesystem paths for the application.
     *
     * Called during boot() after WordPress is available. Paths are stored
     * in the container under the `path.*` prefix and accessible via path().
     *
     * `basePath` is always the App-Root — consistent in both modes:
     * - Classic mode: ${composer_dir}/app/
     * - Theme mode:   get_template_directory()
     *
     * Registered paths:
     * - `path.base`      — project root (where composer.json lives in Classic, theme dir in Theme)
     * - `path.vendor`    — vendor/ directory (relative to composer.json root)
     * - `path.framework` — Sloth src/ directory
     * - `path.cms`       — WordPress ABSPATH
     * - `path.plugins`   — WP_PLUGIN_DIR
     * - `path.theme`     — active theme directory
     * - `path.uploads`   — WordPress uploads base directory
     * - `path.storage`   — basePath/storage/ (auto-created)
     * - `path.cache`     — basePath/storage/cache/ (auto-created)
     * - `path.logs`      — basePath/storage/logs/ (auto-created)
     *
     * @since 1.0.0
     */
    protected function registerBasePaths(): void
    {
        $this->basePath = $this->guessBasePath();

        // basePath is always the App-Root — path() is always relative to it
        $this->addPath('base', $this->basePath);
        $this->addPath('app', $this->basePath);
        $this->addPath('vendor', dirname($this->basePath) . '/vendor');
        $this->addPath('framework', dirname(__DIR__));
        $this->addPath('cms', ABSPATH);
        $this->addPath('plugins', WP_PLUGIN_DIR);
        $this->addPath('theme', get_template_directory());
        $this->addPath('uploads', wp_upload_dir()['basedir']);

        // Storage is always relative to the App-Root — auto-create if missing.
        // Add storage/ to .gitignore.
        $storagePath = $this->basePath . '/storage';

        foreach ([$storagePath, $storagePath . '/cache', $storagePath . '/logs'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
        }

        $this->addPath('storage', $storagePath);
        $this->addPath('cache', $storagePath . '/cache');
        $this->addPath('logs', $storagePath . '/logs');
    }

    /**
     * Register all base URIs for the application.
     *
     * Called during boot() after WordPress is available. URIs are stored
     * in the container under the `uri.*` prefix and accessible via uri().
     * Trailing slashes are stripped for consistency.
     *
     * Registered URIs:
     * - `uri.home`    — WordPress home URL (home_url('/'))
     * - `uri.theme`   — Active theme directory URI
     * - `uri.content` — WordPress content directory URI
     * - `uri.uploads` — WordPress uploads directory URI
     *
     * @since 1.0.0
     */
    protected function registerBaseUris(): void
    {
        if (!function_exists('home_url')) {
            return;
        }

        $this->addUri('home', home_url('/'));
        $this->addUri('theme', get_template_directory_uri());
        $this->addUri('content', content_url());
        $this->addUri('uploads', wp_upload_dir()['baseurl']);
    }

    /**
     * Guess the App-Root path.
     *
     * The App-Root is always the directory where Models, Modules, Providers etc. live:
     * - Classic mode: ${composer_dir}/app/
     * - Theme mode:   get_template_directory()
     *
     * Resolution order:
     * 1. Already cached — return immediately
     * 2. `SLOTH_BASE_PATH` constant — explicit override, used as-is
     * 3. Walk up from ABSPATH to find composer.json outside vendor/ → Classic mode → append /app
     * 4. Theme-only fallback — get_template_directory() → Theme mode
     *
     * Result is cached statically for the duration of the request.
     *
     * @throws RuntimeException if the base path cannot be determined
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
            default => __DIR__,
        });

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json') && !str_contains($dir, '/vendor/')) {
                // Classic mode — App-Root is app/ inside the project root
                return self::$cachedBasePath = $dir . '/app';
            }

            $dir = dirname($dir);
        }

        if (function_exists('get_template_directory')) {
            // Theme mode — App-Root is the theme directory itself
            return self::$cachedBasePath = get_template_directory();
        }

        throw new RuntimeException(
            'Sloth could not determine the project base path. '
            . 'Define SLOTH_BASE_PATH in wp-config.php if your structure is non-standard.',
        );
    }

    /**
     * Add a filesystem path to the container.
     *
     * If the path is an existing directory, realpath() is used to resolve
     * symlinks and normalise the path.
     *
     * @param string $key  Path identifier (e.g. 'cache', 'theme').
     * @param string $path absolute filesystem path
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
     * Add a URI to the container.
     *
     * Trailing slashes are stripped so callers can safely append paths
     * with a leading slash or without.
     *
     * @param string $key URI identifier (e.g. 'home', 'theme').
     * @param string $uri absolute URI
     *
     * @since 1.0.0
     */
    public function addUri(string $key, string $uri): void
    {
        $this->instance('uri.' . $key, rtrim($uri, '/'));
    }

    /**
     * Get the project base path.
     *
     * @param string $path optional sub-path to append
     *
     * @since 1.0.0
     */
    public function basePath(string $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    /**
     * Get a registered filesystem path from the container.
     *
     * @param string $path   optional sub-path to append
     * @param string $prefix path key — see registerBasePaths() for available keys
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
     * Get a registered URI from the container.
     *
     * @param string $path   optional path to append (leading slash is stripped)
     * @param string $prefix URI key — see registerBaseUris() for available keys
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @since 1.0.0
     */
    public function uri(string $path = '', string $prefix = 'home'): string
    {
        $base = $this->get('uri.' . $prefix);

        return $path !== '' && $path !== '0' ? $base . '/' . ltrim($path, '/') : $base;
    }

    /**
     * Get the app config path.
     *
     * Shorthand for path('config').
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @since 1.0.0
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
     * Matches WP_ENV values: 'development', 'develop', 'dev'.
     *
     * @since 1.0.0
     */
    public function isLocal(): bool
    {
        return in_array(env('WP_ENV', 'production'), ['local', 'development', 'develop', 'dev'], true);
    }

    /**
     * Check if running in production.
     *
     * @since 1.0.0
     */
    public function isProduction(): bool
    {
        return !$this->isLocal();
    }

    /**
     * Check if running unit tests.
     *
     * @since 1.0.0
     */
    public function runningUnitTests(): bool
    {
        return defined('WP_TESTS_PHASE') || env('WP_ENV') === 'testing';
    }

    /**
     * Get the current environment name.
     *
     * Returns the value of WP_ENV, defaulting to 'production'.
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
     * Get the class name for a model by its post_type.
     *
     * @param string $key post type slug
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.models')[$key] directly.
     *
     * @since 1.0.0
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
     * @todo Deprecate — use app('sloth.models') directly.
     *
     * @since 1.0.0
     */
    public function getAllModels(): array
    {
        return collect(app('sloth.models'));
    }

    /**
     * Get the class name for a taxonomy by its slug.
     *
     * @param string $key taxonomy slug
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.taxonomies')[$key] directly.
     *
     * @since 1.0.0
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
     * @todo Deprecate — use app('sloth.taxonomies') directly.
     *
     * @since 1.0.0
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
     * Delegates to Illuminate's join_paths() helper.
     *
     * @param string $basePath base path
     * @param string $path     optional sub-path to append
     *
     * @since 1.0.0
     */
    public function joinPaths(string $basePath, string $path = ''): string
    {
        return join_paths($basePath, $path);
    }
}
