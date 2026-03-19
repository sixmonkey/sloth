<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Configure Facade for accessing the configuration service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Configure extends Facade {
	/**
	 * Return the service provider key responsible for the configure class.
	 *
	 * @since 1.0.0
	 *
	 * @return string The service identifier for the configure facade
	 */
	protected static function getFacadeAccessor(): string {
		return 'configure';
	}
}
