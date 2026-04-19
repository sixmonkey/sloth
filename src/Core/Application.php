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
     * Registers the Application class into the container.
     *
     * This allows the application instance to be accessed from
     * the container itself via dependency injection or the
     * static setInstance method.
     *
     * @since 1.0.0
     * @see Application::getInstance()
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

        $this->addPath('base',    $base);
        $this->addPath('app',     $base . '/app');
        $this->addPath('vendor',  $base . '/vendor');
        $this->addPath('cms',     ABSPATH);
        $this->addPath('plugins', WP_PLUGIN_DIR);
        $this->addPath('theme',   get_template_directory());

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
        $dir = __DIR__;

        while ($dir !== '/') {
            if (
                file_exists($dir . '/composer.json')
                && !str_contains($dir, '/vendor/')
            ) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        throw new \RuntimeException('Sloth could not determine the project base path.');
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
     *
     * @return array<int, array{fn: callable, priority: int}>
     * @since 1.0.0
     *
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

    /**
     * Resolves the given type from the container with custom handling.
     *
     * This method provides custom resolution for specific classes like the Paginator.
     *
     * @param class-string|object $abstract The class or interface to resolve
     * @param array<string, mixed> $parameters Constructor parameters
     * @param bool $raiseEvents Whether to fire resolution events
     *
     * @return mixed The resolved instance
     *
     * @throws BindingResolutionException
     * @since 1.1.0 Renamed from resolve to avoid conflict with protected parent method
     *
     * @see \Illuminate\Container\Container::make()
     * @since 1.0.0
     */
    public function resolveFromContainer($abstract, array $parameters = [], $raiseEvents = true): mixed
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
