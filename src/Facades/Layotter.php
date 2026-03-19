<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Layotter Facade for accessing the Layotter page builder service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Layotter extends Facade
{
    /**
     * Return the service provider key responsible for the Layotter class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the Layotter facade
     */
    protected static function getFacadeAccessor(): string
    {
        return 'layotter';
    }
}
