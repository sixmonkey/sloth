<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * Module Facade for accessing the module service.
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module
 */
class Module extends \Sloth\Module\Module
{
    /**
     * Return the service provider key responsible for the module class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the module facade
     */
    protected static function getFacadeAccessor(): string
    {
        return 'module';
    }
}
