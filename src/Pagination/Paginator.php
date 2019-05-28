<?php

namespace Sloth\Pagination;

use Illuminate\Pagination\LengthAwarePaginator as BasePaginator;

class Paginator extends BasePaginator {
    /**
     * @TODO: This one seems very insecure?
     *
     * @param int $page
     *
     * @return string
     */
    public function url( $page ) {
        if ( \is_archive() ) {
            return get_pagenum_link( $page );
        }

        if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
            $current         = $_GET;
            $current['page'] = $page;

            return parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) . '?' . http_build_query( $current );
        }

        $parts = [ rtrim( get_permalink(), ' / ' ) ];
        if ( $page > 1 ) {
            $parts[] = $page;
        }

        return rtrim( implode( ' / ', $parts ), ' / ' ) . ' / ';
    }

}
