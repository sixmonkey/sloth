<?php

declare(strict_types=1);

namespace Sloth\Configure;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Configure component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ConfigureServiceProvider extends ServiceProvider {
	/**
	 * Register the Configure service provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'configure',
			fn($container) => Configure::getInstance()
		);
	}
}
