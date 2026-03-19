<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Pagination Facade for accessing the pagination service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Pagination extends Facade {
	/**
	 * Return the service provider key responsible for the pagination class.
	 *
	 * @since 1.0.0
	 *
	 * @return string The service identifier for the pagination facade
	 */
	protected static function getFacadeAccessor(): string {
		return 'paginaton';
	}
}
