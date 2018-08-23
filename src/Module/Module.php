<?php

namespace Sloth\Module;

use Sloth\Facades\View as View;
use Sloth\Utility\Utility;

class Module {
	public static $layotter = false;
	public static $json = false;
	private $view;
	private $viewVars = [];
	protected $viewPrefix = 'module';
	protected $render = true;
	protected $template;
	public static $ajax_url;
	protected $doing_ajax = false;
	protected $wrapInRow = false;

	public final function __construct( $options = [] ) {
		if ( isset( $options['wrapInRow'] ) ) {
			$this->wrapInRow = $options['wrapInRow'];
		}
	}

	protected function beforeRender() {

	}

	protected function beforeGetJSON() {
	}

	final private function getTemplate() {
		if ( is_null( $this->template ) ) {
			$class          = get_class( $this );
			$this->template = \Cake\Utility\Inflector::dasherize( preg_replace( '/Module$/',
				'',
				substr( strrchr( $class, "\\" ), 1 ) ) );
		}
		if ( ! strstr( $this->template, '.' ) ) {
			$this->template = $this->viewPrefix . '.' . $this->template;
		}
		$this->template = str_replace( '.', DS, ucfirst( $this->template ) );
	}

	final private function makeView() {
		$this->getTemplate();
		$this->view = View::make( $this->template );
	}

	final public function get_layotter_attributes() {
		$class = get_class( $this );

		return $class::$layotter;
	}

	/**
	 * render the view
	 */
	public function render() {
		if ( ! $this->doing_ajax ) {
			$this->set( $GLOBALS['sloth::plugin']->getContext(), false );
		}
		$this->set( 'ajax_url', $this->getAjaxUrl() );
		$this->beforeRender();
		$this->makeView();
		$vars   = array_merge( $GLOBALS['sloth::plugin']->getContext(), $this->viewVars );
		$output = $this->view->with( $vars )->render();
		if ( $this->render ) {
			if ( $this->wrapInRow ) {
				$output = View::make( 'Layotter.row' )->with( [
					'content' => $output,
					'options' => (array) $this->wrapInRow,
				] )->render();
			}
			echo $output;
		}

		return $output;
	}

	final public function set( $key, $value = null, $override = true ) {
		if ( is_array( $key ) ) {
			$override = $value;
			foreach ( $key as $k => $v ) {
				if ( $override || ! $this->isSet( $k ) ) {
					$this->set( $k, $v );
				}
			}
		} else {
			if ( $override || ! $this->isSet( $key ) ) {
				$this->viewVars[ $key ] = $this->_prepareValue( $value );
			}
		}
	}

	final protected function get( $k ) {
		if ( ! isset( $this->viewVars[ $k ] ) ) {
			$this->viewVars[ $k ] = null;
		}

		return $this->viewVars[ $k ];
	}

	final public function isSet( $key ) {
		return isset( $this->viewVars[ $key ] );
	}

	final public function unset( $key ) {
		unset( $this->viewVars[ $key ] );
	}

	final protected function _get( $k ) {
		return $this->get( $k );
	}

	final public function getJSON() {
		$this->doing_ajax = true;
		$this->beforeRender();
		$this->beforeGetJSON();
		header( 'Content-Type: application/json' );
		echo json_encode( $this->viewVars, 1 );
		die();
	}

	final public function getAjaxUrl() {
		return str_replace( home_url(),
			'',
			\admin_url( 'admin-ajax.php?action=' . $this->getAjaxAction() ) );
	}

	final public function getAjaxAction() {
		return 'module_' . Utility::underscore( class_basename( $this ) );
	}

	final protected function _prepareValue( $value ) {
		if ( is_a( $value, 'WP_Post' ) ) {
			$model_name = $GLOBALS['sloth::plugin']->getPostTypeClass( $value->post_type );
			$post       = call_user_func( [ $model_name, 'find' ], $value->ID );
			$value      = $post;
		}

		return $value;
	}
}
