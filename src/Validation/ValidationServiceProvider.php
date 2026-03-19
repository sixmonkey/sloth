<?php

declare(strict_types=1);

namespace Sloth\Validation;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Validation component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ValidationServiceProvider extends ServiceProvider {
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	protected bool $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->singleton(
			'validator',
			fn($app) => new Factory(
				new Translator(new ArrayLoader(), \get_locale()),
				$app
			)
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string>
	 */
	public function provides(): array {
		return [
			'validator',
		];
	}
}
