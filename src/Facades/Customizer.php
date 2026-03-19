<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Customizer Facade for accessing the WordPress customizer service.
 *
 * @since 1.0.0
 * @see \Sloth\Facades\Facade
 */
class Customizer extends Facade
{
    /**
     * Return the service provider key responsible for the customizer class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the customizer facade
     */
    protected static function getFacadeAccessor(): string
    {
        return 'customizer';
    }
}
