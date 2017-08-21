<?php

namespace Sloth\Finder;

use Illuminate\Filesystem\Filesystem;
use Sloth\Core\ServiceProvider;

class FinderServiceProvider extends ServiceProvider {
	public function register() {
		$this->app->bind( 'filesystem',
			function () {
				return new Filesystem();
			} );
	}
}
