<?php

namespace Sloth\Pagination;

use Illuminate\Support\ServiceProvider;

class PaginationServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     */
    public function register()
    {
        \Illuminate\Pagination\AbstractPaginator::viewFactoryResolver(function () {
            return $GLOBALS['sloth']->container['view'];
        });

        \Illuminate\Pagination\AbstractPaginator::$defaultView       = 'Pagination.default';
        \Illuminate\Pagination\AbstractPaginator::$defaultSimpleView = 'Pagination.default';

        \Illuminate\Pagination\AbstractPaginator::currentPathResolver(function () {
            return '';
        });
    }
}
