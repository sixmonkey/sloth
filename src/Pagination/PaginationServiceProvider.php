<?php

namespace Sloth\Pagination;
use Illuminate\Support\ServiceProvider;
class PaginationServiceProvider extends ServiceProvider {
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

	}
	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides() {
		return array('paginator');
	}
}