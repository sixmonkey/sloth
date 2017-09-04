<?php

namespace Sloth\Module;

use Sloth\Facades\View as View;

class Module {
	public static $layotter = false;
	private $view;
	private $viewVars = [];
	protected $viewPrefix = 'module';
	protected $render = true;
	protected $template;

	protected function beforeRender() {

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
	}

	final private function makeView() {
		$this->getTemplate();
		$this->view = View::make( $this->template );
	}

	final public function get_layotter_attributes() {
		$class = get_class( $this );

		return $class::$layotter;
	}

	final public function render() {
		$this->beforeRender();
		if ( $this->render ) {
			$this->makeView();
			echo $this->view->with( $this->viewVars )->render();
		}
	}

	final public function set( $key, $value = null ) {
		if ( is_array( $key ) ) {
			foreach ( $key as $k => $v ) {
				$this->set( $k, $v );
			}
		} else {
			$this->viewVars[ $key ] = $value;
		}
	}

	final protected function get( $k ) {
		if ( ! isset( $this->viewVars[ $k ] ) ) {
			$this->viewVars[ $k ] = null;
		}

		return $this->viewVars[ $k ];
	}

	final protected function _get( $k ) {
		return $this->get( $k );
	}
}
