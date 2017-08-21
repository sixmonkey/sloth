<?php namespace Sloth\Paginator;

use Illuminate\Pagination\Paginator as BasePaginator;

class Paginator extends BasePaginator {

	/**
	 * Get a URL for a given page number.
	 *
	 * @param integer $page
	 * @return string
	 */
	public function getUrl($page) {
		return 'Fucker';
	}

}