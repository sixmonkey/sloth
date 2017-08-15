<?php

namespace Sloth\Plugin;

use Sloth\Core\Sloth;
class Plugin extends \Singleton {

	public function __construct() {
		$this->add_filters();
		$this->loadControllers();
		$this->loadModels();
		#\Route::instance()->boot();
	}

	private function loadControllers() {
		foreach(glob(\get_template_directory() . DS . 'Controller' . DS . '*Controller.php') as $file) {
			include($file);
		}
	}

	private function loadModels() {
		foreach(glob(DIR_APP . 'Model' . DS . '*.php') as $file) {
			include($file);
			bdump($file);
		}
	}

	private function add_filters() {
		add_filter( 'network_admin_url', [ $this, 'fix_network_admin_url' ] );
		add_action( 'init', [ Sloth::getInstance(), 'g' ], 20 );
		#add_action( 'template_redirect', [ Sloth::getInstance(), 'dispatchRouter' ], 20 );
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
			if ( isset( $url_info['query'] ) && ! empty( $url_info['query'] ) ) {
				$url .= '?' . $url_info['query'];
			}
		}

		return $url;
	}
}