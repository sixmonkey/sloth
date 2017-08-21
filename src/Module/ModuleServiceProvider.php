<?php

namespace Sloth\Finder;

use Sloth\Core\ServiceProvider;

class FinderServiceProvider extends ServiceProvider {
	public function register() {
		$this->app->bind( 'module',
			function () {
				return new Module();
			} );
	}
}
