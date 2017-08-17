<?php

namespace Sloth\Module;

class LayotterElement extends \Layotter_Element {
	public static $module;

	public function attributes() {
		$layotter_data     = self::$module->get_layotter_attributes();
		$this->field_group = $layotter_data['field_group'];
		$this->title       = $layotter_data['title'];
		$this->description = $layotter_data['description'];
		$this->icon        = $layotter_data['icon'];
	}

	public function frontend_view( $fields ) {
		self::$module->set( $fields );
		if ( ! is_admin() ) {
			self::$module->render();
		}
	}

	public function backend_view( $fields ) {
		echo '<h1><i class="fa fa-' . $this->icon . '"></i> ' . $this->title . ' </h1>';
		echo '<p>' . $this->description . ' </p>';
	}
}