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
}