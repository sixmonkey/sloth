<?php

declare(strict_types=1);

namespace Sloth\Core;

use Sloth\Debugger\SlothBarPanel;
use Sloth\Route\Route;
use Tracy\Debugger;
use Corcel\Database;
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
     * @var Application
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
        'Route' => '\Sloth\Facades\Route',
        'View' => '\Sloth\Facades\View',
        'Configure' => '\Sloth\Facades\Configure',
        'Validator' => '\Sloth\Facades\Validation',
        'Deployment' => '\Sloth\Facades\Deployment',
        'Customizer' => '\Sloth\Facades\Customizer',
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

        $this->setDebugging();

        $this->container = new Application();
        $this->container->addPath('cache', DIR_CACHE);

        \Sloth\Facades\Facade::setFacadeApplication($this->container);

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
     * @see RouteServiceProvider For routing functionality
     * @see ViewServiceProvider For template rendering
     * @see FinderServiceProvider For file finding
     */
    protected function registerProviders(): void
    {
        $providers = [
            \Sloth\Route\RouteServiceProvider::class,
            \Sloth\Finder\FinderServiceProvider::class,
            \Sloth\View\ViewServiceProvider::class,
            \Sloth\Module\ModuleServiceProvider::class,
            \Sloth\Pagination\PaginationServiceProvider::class,
            \Sloth\Layotter\LayotterServiceProvider::class,
            \Sloth\Configure\ConfigureServiceProvider::class,
            \Sloth\Request\RequestServiceProvider::class,
            \Sloth\Validation\ValidationServiceProvider::class,
            \Sloth\Deployment\DeploymentServiceProvider::class,
            \Sloth\Admin\CustomizerServiceProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->container->register($provider);
        }
    }

    /**
     * Sets up rewrite rules for the router.
     *
     * Hooks into WordPress to register custom route patterns
     * so WordPress knows about Sloth's custom routes.
     *
     * @since 1.0.0
     *
     * @see Route::setRewrite()
     */
    public function setRouter(): void
    {
        $this->container['route']->setRewrite();
    }

    /**
     * Dispatches the router to handle the current request.
     *
     * This method is called during WordPress's template redirect
     * and processes any matching Sloth routes before WordPress
     * falls back to its default template hierarchy.
     *
     * @since 1.0.0
     *
     * @see Route::dispatch()
     */
    public function dispatchRouter(): void
    {
        if (\is_feed() || \is_comment_feed()) {
            return;
        }

        $this->container['route']->dispatch();
    }

    /**
     * Creates class aliases for commonly used framework classes.
     *
     * This allows developers to use short class names like 'Route'
     * instead of the fully qualified '\Sloth\Facades\Route'.
     *
     * @since 1.0.0
     *
     * @example Route::get('/about', ['controller' => 'PageController']);
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
     * Configures and enables the Tracy debugger.
     *
     * The debugger is set to DEVELOPMENT mode when WP_DEBUG is true,
     * otherwise it runs in PRODUCTION mode. Tracy panels are added
     * for vendor versions and custom Sloth debugging information.
     *
     * @since 1.0.0
     *
     * @see Debugger For Tracy debugger configuration
     * @see SlothBarPanel For custom debug panel
     */
    private function setDebugging(): void
    {
        $mode = defined('WP_DEBUG') && \WP_DEBUG === true ? Debugger::DEVELOPMENT : Debugger::PRODUCTION;

        Debugger::$showLocation = true;

        $logDirectory = DIR_ROOT . DS . 'logs';

        if (!is_dir($logDirectory)) {
            mkdir($logDirectory);
        }

        Debugger::getBar()->addPanel(new \Milo\VendorVersions\Panel());
        Debugger::getBar()->addPanel(new SlothBarPanel());

        if (defined('WP_DEBUG') && WP_DEBUG && !in_array(basename($_SERVER['PHP_SELF'] ?? ''), $this->dontDebug, true)) {
            Debugger::enable($mode, DIR_ROOT . DS . 'logs');
        }

        if (getenv('SLOTH_DEBUGGER_EDITOR')) {
            Debugger::$editor = getenv('SLOTH_DEBUGGER_EDITOR');
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
     * @see Database For Corcel database configuration
     * @see \Corcel\Model\Post For post model usage
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
    }
}
