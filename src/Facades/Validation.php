<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Override;

/**
 * Validation Facade for accessing the validation service.
 *
 * @since 1.0.0
 * @see Facade
 */
class Validation extends Facade
{
    /**
     * Return the service provider key responsible for the validation class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the validator facade
     */
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'validator';
    }
}
