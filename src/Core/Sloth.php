<?php

namespace Sloth\Core;

use Sloth\Route\Route;
use Tracy\Debugger;
use Tracy\Dumper;

class Sloth extends \Singleton {

	public $container;

	/**
	 * Classaliases for our Application
	 *
	 * @var array
	 */
	private $class_aliases = [
		'Route' => '\Sloth\Facades\Route',
	];

	public function __construct() {
		/*
		 * Instantiate the service container for the project.
		 */
		$this->container = new \Sloth\Core\Application();

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
		 * enable debugging where needed
		 */
		$this->setDebugging();
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
			#\Sloth\Finder\FinderServiceProvider::class,
			#\Sloth\View\ViewServiceProvider::class,
			#\Sloth\Module\ModuleServiceProvider::class,
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
		var_dump( 'HALLO' );
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
		if ( WP_DEBUG ) {
			\Tracy\Debugger::enable( $mode );
		}
	}
}