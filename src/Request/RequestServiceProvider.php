<?php

namespace Sloth\Request;

use Sloth\Core\ServiceProvider;
use Illuminate\Http\Request;

class RequestServiceProvider extends ServiceProvider {
	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register() {

		$this->app->singleton( 'request',
			function ( $app ) {
				$request = Request::capture();

				return $request;
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
