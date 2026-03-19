<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Route Facade for accessing the routing service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Route extends Facade {
	/**
	 * Return the service provider key responsible for the route class.
	 *
	 * @since 1.0.0
	 *
	 * @return string The service identifier for the route facade
	 */
	protected static function getFacadeAccessor(): string {
		return 'route';
	}
}
