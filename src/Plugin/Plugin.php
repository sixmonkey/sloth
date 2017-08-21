<?php

namespace Sloth\Plugin;

use PostTypes\PostType;

use Sloth\Core\Sloth;

class Plugin extends \Singleton {
	public $current_theme_path;
	private $container;

	public function __construct() {
		$this->add_filters();
		$this->loadControllers();
		$this->loadModels();
		#\Route::instance()->boot();

		/**
		 * set current_theme_path
		 */
		$this->current_theme_path = realpath( get_template_directory() );
		/**
		 * tell container about current theme oath
		 */
		$GLOBALS['sloth']->container->addPath( 'theme', $this->current_theme_path );

		/**
		 * add templates to viewFinder
		 */
		$this->addTemplates();
		/*
		 * we need the possibility to use @extends in twig, so we resolve this one as regular path
		 */
		$twigLoader = $GLOBALS['sloth']->container['twig.loader'];
		$twigLoader->addPath( $this->current_theme_path . DS . 'views' . DS . 'partials', 'partials' );

	}

	private function loadControllers() {
		foreach ( glob( \get_template_directory() . DS . 'Controller' . DS . '*Controller.php' ) as $file ) {
			include( $file );
		}
	}

	public function loadModels() {

		foreach ( glob( DIR_APP . 'Model' . DS . '*.php' ) as $file ) {
			include( $file );
			$classes    = get_declared_classes();
			$model_name = array_pop( $classes );

			$model = new $model_name;
			$model->register();
		}
	}

	public function loadModules() {
		foreach ( glob( get_template_directory() . DS . 'Module' . DS . '*Module.php' ) as $file ) {
			include( $file );
			$classes     = get_declared_classes();
			$module_name = array_pop( $classes );

			if ( is_array( $module_name::$layotter ) && class_exists( '\Layotter' ) ) {

				$class_name = substr( strrchr( $module_name, "\\" ), 1 );

				eval( "class $class_name extends \Sloth\Module\LayotterElement {
					static \$module = '$module_name';
				}" );
				\Layotter::register_element( strtolower( substr( strrchr( $module_name, "\\" ), 1 ) ), $class_name );
			}
		}
	}

	private function add_filters() {

		add_filter( 'network_admin_url', [ $this, 'fix_network_admin_url' ] );
		add_action( 'init', [ $this, 'loadModules' ], 20 );
		#add_action( 'init', [ Sloth::getInstance(), 'setRouter' ], 20 );
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


	/*
	 *  add required templates
	 */
	private function addTemplates() {
		$viewFinder = $GLOBALS['sloth']->container['view.finder'];

		$viewFinder->addNamespace( 'module',
			$GLOBALS['sloth']->container['path.theme'] . DS . 'View' . DS . 'Module' . DS );
		$viewFinder->addNamespace( 'module_backend',
			$GLOBALS['sloth']->container['path.theme'] . DS . 'View' . DS . 'Module' . DS . 'backend' );

	}
}