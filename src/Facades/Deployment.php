<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Deployment Facade for accessing the deployment service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Deployment extends Facade
{
    /**
     * Return the service provider key responsible for the deployment class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the deployment facade
     */
    protected static function getFacadeAccessor(): string
    {
        return 'deployment';
    }
}
