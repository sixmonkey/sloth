<?php

declare(strict_types=1);

namespace Sloth\Pagination;

use Illuminate\Pagination\AbstractPaginator;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Pagination component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class PaginationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @since 1.0.0
     */
    protected bool $defer = true;

    /**
     * Register the service provider.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        AbstractPaginator::viewFactoryResolver(fn() => $this->app['view']);

        AbstractPaginator::$defaultView       = 'Pagination.default';
        AbstractPaginator::$defaultSimpleView = 'Pagination.default';

        AbstractPaginator::currentPathResolver(fn(): string => '');
    }

    /**
     * Get the services provided by the provider.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function provides(): array
    {
        return [
            'paginaton',
        ];
    }
}
