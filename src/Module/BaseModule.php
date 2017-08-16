<?php
/**
 * User: Kremer
 * Date: 16.08.17
 * Time: 00:33
 */

namespace Sloth\Module;

use Sloth\Facades\View as View;


trait BaseModule {

	public static $layotter = false;
	protected $view;
	protected $viewVars = [];

	final public function attributes() {
		$class             = get_class( $this );
		$layotter          = $class::$layotter;
		$this->title       = $layotter['title'];
		$this->description = $layotter['description'];
		$this->field_group = $layotter['field_group'];
		$this->icon        = $layotter['icon'];
	}

	private final function makeView( $backend = false ) {
		$template = $this->getTemplate();
		if ( $backend ) {
			$template .= '_backend';
		}
		$this->view = View::make( 'module::' . $template );
	}

	final public function frontend_view( $fields ) {
		if ( ! is_admin() ) {
			foreach ( $fields as $k => $v ) {
				$this->set( $k, $v );
			}
			$this->makeView();
			$this->beforeRender();
			echo $this->view->with( $this->viewVars )->render();
		}
	}

	final public function backend_view( $fields ) {
		$this->renderBackend();
	}

	public function renderBackend() {
		echo '<h1><i class="fa fa-' . $this->icon . '" /> ' . $this->title . '</h1>';
		echo '<p>' . $this->description . '</p>';
	}

	final protected function set( $key, $value ) {
		$this->viewVars[ $key ] = $value;
	}
	final protected function _get( $key ) {
		return $this->viewVars[ $key ];
	}

	protected function beforeRender() {

	}

	final private function getTemplate() {
		$class = get_class( $this );
		$name  = strtolower( preg_replace( '/Module$/', '', substr( strrchr( $class, "\\" ), 1 ) ) );

		return $name;
	}

}