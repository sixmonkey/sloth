<?php

declare(strict_types=1);
namespace Sloth\Pagination;

use Illuminate\Pagination\AbstractPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Pagination component.
 *
 * Integrates `illuminate/pagination` with WordPress by configuring
 * Laravel's paginator resolvers (current page, current path, query
 * string, view factory) from a single config file.
 *
 * ## What it does
 *
 * - Merges the pagination config so themes can override individual
 *   settings by publishing the config file
 * - Sets the `currentPageResolver` to read the current page from
 *   WordPress request data instead of Laravel's default request()
 * - Configures the Twig view factory as the pagination link renderer
 * - Auto-appends all current query string parameters via config-
 *   controlled ->withQueryString() call on every paginator instance
 * - Binds `LengthAwarePaginator` to the container so that Eloquent's
 *   ->paginate() returns a fully configured paginator
 *
 * ## Why a container binding?
 *
 * Laravel's Eloquent `Builder::paginate()` internally calls
 * `Container::makeWith(LengthAwarePaginator::class, $params)`.
 * Without this binding a plain `LengthAwarePaginator` would be
 * returned, missing the ->withQueryString() call. The closure
 * binding intercepts construction and applies the configured
 * resolvers automatically.
 *
 * ## Configuration
 *
 * All resolver callbacks live in `config/pagination.php` and can
 * be overridden per project:
 *
 * ```php
 * // config/pagination.php
 * 'current_page_resolver' => fn(): int => my_custom_page_logic(),
 * 'with_query_string' => false,
 * ```
 *
 * @since 1.0.0
 * @see AbstractPaginator For the available static resolvers
 * @see LengthAwarePaginator For the paginator contract
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
     * Register the pagination service.
     *
     * Merges the default config and configures all paginator resolvers
     * (view factory, current page, current path, query string). Also
     * binds `LengthAwarePaginator` so that every paginator created via
     * the container automatically receives the configured query string
     * behaviour.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/pagination.php', 'pagination');

        AbstractPaginator::viewFactoryResolver(config('pagination.view_factory_resolver'));

        AbstractPaginator::$defaultView = config('pagination.default_view');
        AbstractPaginator::$defaultSimpleView = config('pagination.default_simple_view');

        AbstractPaginator::currentPathResolver(config('pagination.current_path_resolver'));

        AbstractPaginator::queryStringResolver(config('pagination.query_string_resolver'));

        AbstractPaginator::currentPageResolver(config('pagination.current_page_resolver'));

        $this->app->bind(LengthAwarePaginator::class, function ($app, array $parameters): LengthAwarePaginator {
            $paginator = new LengthAwarePaginator(
                $parameters['items'],
                $parameters['total'],
                $parameters['perPage'],
                $parameters['currentPage'],
                $parameters['options'] ?? [],
            );

            if (config('pagination.with_query_string')) {
                $paginator->withQueryString();
            }

            return $paginator;
        });
    }
}
