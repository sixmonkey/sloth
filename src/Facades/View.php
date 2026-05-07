<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Illuminate\View\Factory;
use Override;

/**
 * View Facade for accessing the view rendering service.
 *
 * @since 1.0.0
 * @see Facade
 *
 * @mixin Factory
 */
class View extends Facade
{
    /**
     * Return the service provider key responsible for the view class.
     *
     * @since 1.0.0
     *
     * @return string The service identifier for the view facade
     */
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}
