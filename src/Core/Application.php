<?php

declare(strict_types=1);
namespace Sloth\Core;

use Illuminate\Container\Container;
use Sloth\Core\Traits\ApplicationConvenienceTrait;
use Sloth\Core\Traits\ApplicationEnvironmentTrait;
use Sloth\Core\Traits\ApplicationPathTrait;
use Sloth\Core\Traits\ApplicationProviderTrait;
use Sloth\Core\Traits\ApplicationUriTrait;
use Sloth\Facades\Facade;

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
 * app()->themePath()             // get_template_directory()
 * app()->configPath()            // app/config/
 * app()->cachePath()             // theme/storage/cache/
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
    use ApplicationPathTrait;

    use ApplicationUriTrait;

    use ApplicationEnvironmentTrait;

    use ApplicationProviderTrait;

    use ApplicationConvenienceTrait;

    // -------------------------------------------------------------------------
    // Constants & static state
    // -------------------------------------------------------------------------

    /**
     * Application version.
     *
     * @since 1.0.0
     */
    public const version = '2.0.0';

    /**
     * Whether the application has already been booted.
     *
     * @since 1.0.0
     */
    private static bool $booted = false;

    // -------------------------------------------------------------------------
    // Instance state
    // -------------------------------------------------------------------------

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
        'Customizer' => \Sloth\Facades\Customizer::class,
        'Deployment' => \Sloth\Facades\Deployment::class,
        'File'       => \Sloth\Facades\File::class,
        'Validator'  => \Sloth\Facades\Validation::class,
        'View'       => \Sloth\Facades\View::class,
        'URL'        => \Sloth\Facades\URL::class,
        'Options'    => \Sloth\Facades\Options::class,
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
        Facade::setFacadeApplication($this);
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);

        // Load .env before providers boot so env() is available everywhere.
        // basePath is guessed here and cached — no performance penalty on
        // the second call in registerBasePaths().
        $this->loadEnvironment();
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
        if (self::$booted) {
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
}
