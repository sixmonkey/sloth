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

		$parts = [ rtrim( get_permalink(), '/' ) ];
		if ( $page > 1 ) {
			$parts[] = $page;
		}

		return rtrim( implode( '/', $parts ), '/' ) . '/';
	}

}