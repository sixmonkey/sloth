<?php

namespace Sloth\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;
use Illuminate\Events\Dispatcher;

class ValidationServiceProvider extends ServiceProvider {
	public function register() {
		$this->app->singleton( 'validator',
			function ( $container ) {
				$loader  = new ArrayLoader();
				$factory = new Factory(
					new Translator( $loader, \get_locale() ),
					$container );

				return $factory;
			} );
	}
}
