<?php

namespace Sloth\Plugin;

class Plugin {
	/**
	 * Sloth\Plugin instance.
	 *
	 * @var \Sloth\Core\Sloth
	 */
	protected static $instance = null;

	/**
	 * Retrieve Sloth class instance.
	 *
	 * @return \Sloth\Core\Sloth
	 */
	public static function instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	public function __construct() {
		$this->add_filters();
	}

	private function add_filters() {
		add_filter( 'network_admin_url', [ $this, 'fix_network_admin_url' ] );
	}

	public function plugin() {
		// rewrite the upload directory
		add_filter( 'upload_dir',
			function ( $uploads_array ) {
				$fixed_uploads_array = [];
				foreach ( $uploads_array as $part => $value ) {
					if ( in_array( $part, [ 'path', 'url', 'basedir', 'baseurl' ] ) ) {
						$fixed_uploads_array[ $part ] = str_replace( WP_PATH . '/..', '', $value );
					} else {
						$fixed_uploads_array[ $part ] = $value;
					}
				}

				return $fixed_uploads_array;
			} );
	}

	public function fix_network_admin_url( $url ) {
		$url_info = parse_url( $url );
		if ( ! preg_match( '/^\/cms/', $url_info['path'] ) ) {
			$url = $url_info['scheme'] . '://' . $url_info['host'] . '/cms' . $url_info['path'];
		}

		return $url;
	}
}