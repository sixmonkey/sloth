<?php

declare(strict_types=1);

namespace Sloth\Module;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Module component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ModuleServiceProvider extends ServiceProvider {
	/**
	 * Register the Module service provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->bind(
			'module',
			fn(): Module => new Module()
		);
	}
}
