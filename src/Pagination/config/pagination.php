<?php

declare(strict_types=1);

/**
 * Pagination Configuration.
 *
 * Controls how Sloth integrates Laravel's paginator with WordPress.
 * All resolvers are configurable so themes can override behaviour
 * without modifying framework code.
 *
 * Publish to your project to customise:
 *
 * ```bash
 * wp sloth vendor:publish \
 *     --provider="Sloth\Pagination\PaginationServiceProvider" \
 *     --tag=config
 * ```
 *
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Default Pagination View
    |--------------------------------------------------------------------------
    |
    | The Twig view used to render pagination links. Both the full and
    | simple paginator point to the same view by default.
    |
    */

    'default_view' => 'Pagination.default',

    'default_simple_view' => 'Pagination.default',

    /*
    |--------------------------------------------------------------------------
    | View Factory Resolver
    |--------------------------------------------------------------------------
    |
    | Returns the Twig view factory that renders pagination link templates.
    | This is set early so that ->links() and ->render() work automatically
    | without the caller having to specify a view engine.
    |
    */

    'view_factory_resolver' => fn (): mixed => app('view'),

    /*
    |--------------------------------------------------------------------------
    | Current Page Resolver
    |--------------------------------------------------------------------------
    |
    | Determines the current page number from the WordPress request.
    | Falls back through three sources:
    |
    | 1. $_GET['page']             — static front page
    | 2. $wp_query->query['page']  — paginated singular content
    | 3. $wp_query->query['paged'] — archive pages
    |
    | Each value is cast to int so the paginator always receives a safe
    | numeric page number, defaulting to 1 when nothing is set.
    |
    */

    'current_page_resolver' => function (): int {
        if (isset($_GET['page'])) {
            return (int) $_GET['page'];
        }

        global $wp_query;

        if (isset($wp_query->query['page'])) {
            return (int) $wp_query->query['page'];
        }

        if (isset($wp_query->query['paged'])) {
            return (int) $wp_query->query['paged'];
        }

        return 1;
    },

    /*
    |--------------------------------------------------------------------------
    | Current Path Resolver
    |--------------------------------------------------------------------------
    |
    | The base URL used for paginated links. When 'relative_urls' is
    | false (default) the full WordPress home URL is used, producing
    | absolute URLs like https://example.com/page/2.
    |
    | Set 'relative_urls' to true if you want relative paths instead:
    | /page/2 instead of https://example.com/page/2.
    |
    */

    'relative_urls' => false,

    'current_path_resolver' => function (): string {
        $path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

        if (!config('pagination.relative_urls')) {
            return home_url($path);
        }

        return $path;
    },

    /*
    |--------------------------------------------------------------------------
    | Query String Resolver
    |--------------------------------------------------------------------------
    |
    | Returns the current query parameters that should be preserved across
    | paginated URLs. Defaults to all $_GET values so that search queries,
    | filter parameters, UTM tags, etc. survive page changes.
    |
    | Set to null and disable with_query_string below if you want full
    | manual control via ->appends() on the paginator instance.
    |
    */

    'query_string_resolver' => fn (): array => $_GET,

    /*
    |--------------------------------------------------------------------------
    | Auto-append Query String
    |--------------------------------------------------------------------------
    |
    | When true, ->withQueryString() is called on every paginator instance
    | so that all current query parameters automatically appear in every
    | pagination link without explicit ->appends() calls.
    |
    | Set to false if you prefer to call ->appends() or ->withQueryString()
    | manually in your theme code.
    |
    */

    'with_query_string' => true,
];
