<?php
/**
 * Created by PhpStorm.
 * User: Kremer
 * Date: 03.04.17
 * Time: 01:39
 */

namespace Sloth\Controller;


abstract class Controller {
	protected $viewVars = [];

	public function __construct() {
	}

	public function beforeRender() {
	}

	public function afterRender( $output ) {
		return $output;
	}

	public function invokeAction( $request ) {
		$method = new \ReflectionMethod( $this, $request->params['action'] );
		$this->beforeRender();
		$method->invokeArgs( $this, $request->params['pass'] );
		$output = $this->_render();
		$output = $this->afterRender( $output );
		echo $output;
	}

	final private function _render() {
		return 'Hallo!';
	}

	final private function set( $key, $value ) {
		$this->viewVars[ $key ] = $value;
	}

	final private function get( $key ) {
	}
}