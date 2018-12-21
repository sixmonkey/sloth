<?php

namespace Sloth\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Sloth\Core\ServiceProvider;
use Illuminate\Validation\Factory;

class ValidationServiceProvider extends ServiceProvider {
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {

		$this->app->singleton( 'validator',
			function ( $app ) {
				$validator = new Factory( new Translator(new ArrayLoader(), \get_locale()), $app );


				return $validator;
			} );
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return [
			'validator',
		];
	}
}
