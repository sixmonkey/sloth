<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Validation Facade for accessing the validation service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Validation extends Facade {
	/**
	 * Return the service provider key responsible for the validation class.
	 *
	 * @since 1.0.0
	 *
	 * @return string The service identifier for the validator facade
	 */
	protected static function getFacadeAccessor(): string {
		return 'validator';
	}
}
