<?php

declare(strict_types=1);

namespace Sloth\Core;

use Corcel\Database;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Sloth\Debug\Panels\SlothBarPanel;
use Sloth\Facades\Facade;
use Sloth\Singleton\Singleton;

/**
 * Sloth Framework Bootstrap
 *
 * This is the main entry point for the Sloth framework.
 * It initializes the application container, registers service providers,
 * sets up facades, and establishes the database connection.
 *
 * @since 1.0.0
 * @see Singleton For singleton pattern implementation
 */
class Sloth extends Singleton
{
    /**
     * The application container instance.
     *
     * @since 1.0.0
     */
    public Application $container;

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
        'View' => \Sloth\Facades\View::class,
        'Configure' => \Sloth\Facades\Configure::class,
        'Validator' => \Sloth\Facades\Validation::class,
        'Deployment' => \Sloth\Facades\Deployment::class,
        'Customizer' => \Sloth\Facades\Customizer::class,
    ];

    /**
     * Scripts that should not trigger debug output.
     *
     * These are typically AJAX or media upload endpoints where
     * debug output would interfere with the expected response.
     *
     * @since 1.0.0
     * @var array<string>
     */
    private array $dontDebug = [
        'admin-ajax.php',
        'async-upload.php',
    ];

    /**
     * Creates a new Sloth instance.
     *
     * Initializes the framework by:
     * 1. Loading development configuration
     * 2. Setting up debugging
     * 3. Creating the application container
     * 4. Registering all service providers
     * 5. Setting up class aliases
     * 6. Connecting to the WordPress database via Corcel
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        @include(DIR_ROOT . DS . 'develop.config.php');

        $this->container = new Application();
        if (Facade::getFacadeApplication() !== null && Facade::getFacadeApplication()->bound('config')) {
            $existingConfig = Facade::getFacadeApplication()->make('config');
            $this->container->singleton('config', fn() => $existingConfig);
        } else {
            $this->container->singleton('config', fn() => new \Illuminate\Config\Repository([]));
            $this->loadConfigFiles();
        }
        Facade::setFacadeApplication($this->container);

        collect([
            'app' => DIR_APP,
            'cache' => DIR_CACHE,
            'vendor' => DIR_VENDOR,
            'public' => DIR_WWW,
            'plugins' => DIR_PLUGINS,
            'cms' => DIR_CMS,
        ])->each(fn($path, $key) => $this->container->addPath($key, $path));

        $this->registerProviders();

        $this->setAliases();

        $this->connectCorcel();
    }

    /**
     * Registers all core framework service providers.
     *
     * Service providers are registered in the order they appear in the array.
     * Each provider is responsible for bootstrapping specific framework features.
     *
     * @since 1.0.0
     *
     * @see ViewServiceProvider For template rendering
     * @see FinderServiceProvider For file finding
     */
    protected function registerProviders(): void
    {
        $providers = [
            \Sloth\Debug\DebugServiceProvider::class,
            \Sloth\Finder\FinderServiceProvider::class,
            \Sloth\View\ViewServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Pagination\PaginationServiceProvider::class,
            \Sloth\Layotter\LayotterServiceProvider::class,
            \Sloth\Configure\ConfigureServiceProvider::class,
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
            $this->container->register($provider);
        }
    }

    /**
     * Creates class aliases for commonly used framework classes.
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

    /**
     * Establishes a database connection for Corcel.
     *
     * Corcel is used to access WordPress data as Eloquent models.
     * The connection parameters are read from WordPress constants.
     *
     * @since 1.0.0
     *
     * @see  Database For Corcel database configuration
     * @see  \Corcel\Model\Post For post model usage
     *
     * @uses DB_HOST WordPress database host constant
     * @uses DB_NAME WordPress database name constant
     * @uses DB_USER WordPress database user constant
     * @uses DB_PASSWORD WordPress database password constant
     * @uses DB_PREFIX WordPress table prefix constant
     */
    private function connectCorcel(): void
    {
        $params = [
            'host' => DB_HOST,
            'database' => DB_NAME,
            'username' => DB_USER,
            'password' => DB_PASSWORD,
            'prefix' => DB_PREFIX,
        ];

        Database::connect($params);
        Model::setEventDispatcher(new Dispatcher($this->container));
    }

    /**
     * Loads configuration files from app/config/ into the Laravel config repository.
     *
     * Each PHP file in the config directory becomes a config key.
     * For example, app/config/theme.php becomes accessible via config('theme').
     *
     * @since 1.0.0
     */
    private function loadConfigFiles(): void
    {
        $configPath = defined('DIR_CFG') ? DIR_CFG : null;
        if (!$configPath || !is_dir($configPath)) {
            return;
        }

        foreach (glob($configPath . '*.php') as $file) {
            $key = basename($file, '.php');
            $this->container['config']->set($key, require $file);
        }
    }
}
