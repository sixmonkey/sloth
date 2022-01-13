<?php

namespace Sloth\Core;

use Sloth\Debugger\SlothBarPanel;
use Tracy\Debugger;
use Tracy\Dumper;

class Sloth extends \Singleton
{
    public $container;

    /**
     * Classaliases for our Application
     *
     * @var array
     */
    private $class_aliases = [
        'Route'      => '\Sloth\Facades\Route',
        'View'       => '\Sloth\Facades\View',
        'Configure'  => '\Sloth\Facades\Configure',
        'Validator'  => '\Sloth\Facades\Validation',
        'Deployment' => '\Sloth\Facades\Deployment',
        'Customizer' => '\Sloth\Facades\Customizer',
    ];

    private $dont_debug = ['admin-ajax.php', 'async-upload.php'];

    public function __construct()
    {
        /**
         * enable debugging where needed
         */
        $this->setDebugging();

        /*
         * Instantiate the service container for the project.
         */
        $this->container = new \Sloth\Core\Application();

        $this->container->addPath('cache', DIR_CACHE);

        /*
         * Setup the facade.
         */
        \Sloth\Facades\Facade::setFacadeApplication($this->container);


        $this->registerProviders();

        /**
         * Set aliases for common classes
         */
        $this->setAliases();
    }

    /**
     * Hook into front-end routing.
     * Setup the router API to be executed before
     * theme default templates.
     */
    public function dispatchRouter()
    {
        if (is_feed() || is_comment_feed()) {
            return;
        }
        $this->container['route']->dispatch();
    }

    /**
     * Hook into front-end routing.
     * Setup the router API to be executed before
     * theme default templates.
     */
    public function setRouter()
    {
        $this->container['route']->setRewrite();
    }

    /**
     * Register core framework service providers.
     */
    protected function registerProviders()
    {
        /*
         * Service providers.
         */
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
     * Set some aliases for commonly used classes
     */
    private function setAliases()
    {
        foreach ($this->class_aliases as $alias => $class) {
            if (! class_exists($alias)) {
                class_alias($class, $alias);
            }
        }
    }

    /**
     * Set Debugging
     */
    private function setDebugging()
    {
        $mode                   = WP_DEBUG === true ? Debugger::DEVELOPMENT : Debugger::PRODUCTION;
        Debugger::$showLocation = Dumper::LOCATION_CLASS | Dumper::LOCATION_LINK | Dumper::LOCATION_SOURCE;  // Shows both paths to the classes and link to where the dump() was called
        $logDirectoy            = DIR_ROOT . DS . 'logs';
        if (! is_dir($logDirectoy)) {
            mkdir($logDirectoy);
        }
        Debugger::getBar()->addPanel(new \Nofutur3\GitPanel\Diagnostics\Panel());
        Debugger::getBar()->addPanel(new \Milo\VendorVersions\Panel);
        Debugger::getBar()->addPanel(new SlothBarPanel());
        /* TODO: could be nicer? */
        if (WP_DEBUG && ! in_array(basename($_SERVER['PHP_SELF']), $this->dont_debug)) {
            Debugger::enable($mode, DIR_ROOT . DS . 'logs');
        }
        if (getenv('SLOTH_DEBUGGER_EDITOR')) {
            Debugger::$editor = getenv('SLOTH_DEBUGGER_EDITOR');
        }
    }
}
