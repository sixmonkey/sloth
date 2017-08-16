<?php

namespace Sloth\Module;

use Sloth\Facades\View as View;

class Module {
	public static $layotter = false;
	private $view;
	private $viewVars = [];

	protected function beforeRender() {

	}

	final private function getTemplate() {
		$class = get_class( $this );
		$name  = strtolower( preg_replace( '/Module$/', '', substr( strrchr( $class, "\\" ), 1 ) ) );

		return $name;
	}

	final private function makeView() {
		$template   = $this->getTemplate();
		$this->view = View::make( 'module::' . $template );
	}

	final public function get_layotter_attributes() {
		$class = get_class( $this );

		return $class::$layotter;
	}

	final public function render() {
		$this->makeView();
		$this->beforeRender();
		echo $this->view->with( $this->viewVars )->render();
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
		return $this->viewVars[ $k ];
	}

	final protected function _get( $k ) {
		return $this->get( $k );
	}
}
