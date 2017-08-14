<?php

namespace Sloth\Core;

class Sloth extends \Singleton {

	public $container;

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
	}

	/**
	 * Register core framework service providers.
	 */
	protected function registerProviders() {
		/*
		 * Service providers.
		 */
		$providers = [
			#\Sloth\Route\RouteServiceProvider::class,
			#\Sloth\Finder\FinderServiceProvider::class,
			#\Sloth\View\ViewServiceProvider::class,
			#\Sloth\Module\ModuleServiceProvider::class,
		];

		foreach ( $providers as $provider ) {
			$this->container->register( $provider );
		}
	}
}