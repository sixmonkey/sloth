<?php

declare(strict_types=1);

namespace Sloth\Pagination;

use Illuminate\Pagination\LengthAwarePaginator as BasePaginator;

/**
 * Custom paginator with WordPress integration.
 *
 * @since 1.0.0
 * @extends BasePaginator
 */
class Paginator extends BasePaginator
{
    /**
     * Get the URL for a given page number.
     *
     * @since 1.0.0
     *
     * @param int $page The page number
     *
     * @return string The URL for the given page
     */
    public function url(int $page): string
    {
        if (\is_archive()) {
            return (string) get_pagenum_link($page);
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            $current = $_GET;
            $current['page'] = $page;

            $baseUrl = parse_url((string) rest_url('/'), PHP_URL_PATH);
            $here = preg_replace('#' . $baseUrl . '#', '', parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));

            return (string) rest_url($here) . '?' . http_build_query($current);
        }

        $parts = [rtrim((string) get_permalink(), '/')];
        if ($page > 1) {
            $parts[] = $page;
        }

        return rtrim(implode('/', $parts), '/') . '/';
    }
}
