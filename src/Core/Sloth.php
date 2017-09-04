<?php

namespace Sloth\Core;

use Sloth\Route\Route;
use Tracy\Debugger;
use Tracy\Dumper;
use Corcel\Database;

class Sloth extends \Singleton {

	public $container;

	/**
	 * Classaliases for our Application
	 *
	 * @var array
	 */
	private $class_aliases = [
		'Route' => '\Sloth\Facades\Route',
		'View'  => '\Sloth\Facades\View',
	];

	private $dont_debug = [ 'admin-ajax.php', 'async-upload.php' ];

	public function __construct() {
		/**
		 * enable debugging where needed
		 */
		$this->setDebugging();

		/*
		 * Instantiate the service container for the project.
		 */
		$this->container = new \Sloth\Core\Application();

		$this->container->addPath( 'cache', DIR_CACHE );

		/*
         * Setup the facade.
         */
		\Sloth\Facades\Facade::setFacadeApplication( $this->container );


		$this->registerProviders();

		/**
		 * Set aliases for common classes
		 */
		$this->setAliases();

		/**
		 * open database connection for corcel
		 */
		$this->connectCorcel();
	}

	/**
	 * Register core framework service providers.
	 */
	protected function registerProviders() {
		/*
		 * Service providers.
		 */
		$providers = [
			\Sloth\Route\RouteServiceProvider::class,
			\Sloth\Finder\FinderServiceProvider::class,
			\Sloth\View\ViewServiceProvider::class,
			\Sloth\Module\ModuleServiceProvider::class,
			\Sloth\Pagination\PaginationServiceProvider::class,
		];

		foreach ( $providers as $provider ) {
			$this->container->register( $provider );
		}
	}

	/**
	 * Hook into front-end routing.
	 * Setup the router API to be executed before
	 * theme default templates.
	 */
	public function setRouter() {
		$this->container['route']->setRewrite();
	}

	/**
	 * Hook into front-end routing.
	 * Setup the router API to be executed before
	 * theme default templates.
	 */
	public function dispatchRouter() {
		if ( is_feed() || is_comment_feed() ) {
			return;
		}
		$this->container['route']->dispatch();
	}

	/*
	 * automagically add all subdirs off [theme]/views to viewFinder
	 */
	private function addTemplates() {
		$viewFinder = $this->container['view.finder'];
		$view_paths = glob( $this->container['path.theme'] . DS . 'views' . DS . '*' );
		foreach ( $view_paths as $path ) {
			$viewFinder->addNamespace( basename( $path ), $path );
		}
	}

	/**
	 * Set some aliases for commonly used classes
	 */
	private function setAliases() {
		foreach ( $this->class_aliases as $alias => $class ) {
			class_alias( $class, $alias );
		}
	}

	/**
	 * Set Debugging
	 */
	private function setDebugging() {
		$mode                   = WP_DEBUG ? Debugger::DEVELOPMENT : \Tracy\Debugger::PRODUCTION;
		Debugger::$showLocation = Dumper::LOCATION_CLASS | Dumper::LOCATION_LINK | Dumper::LOCATION_SOURCE;  // Shows both paths to the classes and link to where the dump() was called
		/* TODO: could be nicer? */
		if ( WP_DEBUG && ! in_array( basename( $_SERVER['PHP_SELF'] ), $this->dont_debug ) ) {
			Debugger::enable( $mode );
		}
	}

	private function connectCorcel() {
		$params = [
			'host'     => DB_HOST,
			'database' => DB_NAME,
			'username' => DB_USER,
			'password' => DB_PASSWORD,
			'prefix'   => DB_PREFIX // default prefix is 'wp_', you can change to your own prefix
		];
		\Corcel\Database::connect( $params );
	}
}