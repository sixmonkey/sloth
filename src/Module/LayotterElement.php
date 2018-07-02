<?php

namespace Sloth\Module;

use Sloth\Facades\Configure;
use Sloth\Facades\View;
use Sloth\Field\Image;

class LayotterElement extends \Layotter_Element {
	public static $module;

	public function attributes() {
		$class_name  = get_class( $this );
		$module_name = $class_name::$module;
		$module      = new $module_name;

		$layotter_data     = $module->get_layotter_attributes();
		$this->field_group = $layotter_data['field_group'];
		$this->title       = $layotter_data['title'];
		$this->description = $layotter_data['description'];
		$this->icon        = $layotter_data['icon'];
	}

	public function frontend_view( $fields ) {

		$fields = $this->prepare_fields( $fields );


		$options = func_get_args();

		$fields['_layotter.passed'] = [
			'class'        => $options[1],
			'col_options'  => $options[2],
			'row_options'  => $options[3],
			'post_options' => $options[4],
		];


		$class_name  = get_class( $this );
		$module_name = $class_name::$module;
		$module      = new $module_name;
		$module->set( $fields );
		if ( ! is_admin() ) {
			$module->render();
		}
	}

	public function backend_view( $fields ) {
		$fields = $this->prepare_fields( $fields );


		echo '<h1><i class="fa fa-' . $this->icon . '"></i> ' . $this->title . ' </h1>';

		echo '<table class="layotter-preview">';
		foreach ( $this->get_fields() as $field ) {

			if ( isset( $fields[ $field['name'] ] ) ) {
				if ( is_a( $fields[ $field['name'] ], 'Sloth\Field\Image' ) ) {
					$v = '<img src="' . $fields[ $field['name'] ] . '" />';
				} else if ( $field['type'] == 'repeater' ) {
					$v = count( $fields[ $field['name'] ] ) . ' ' . __( 'Elemente', 'sloth' );
				} else if ( is_object( $fields[ $field['name'] ] ) || is_object( $fields[ $field['name'] ] ) || $field['type'] == 'true_false' ) {
					continue;
				} else if ( is_array( $fields[ $field['name'] ] ) ) {
					$v = implode( '<br />', $fields[ $field['name'] ] );
				} else {
					$v = wp_trim_words( $fields[ $field['name'] ], 30 );
				}

				echo '<tr>';
				echo "<th style=\"text-align: left;border-bottom: 1px solid red;\" valign='top'>" . $field['label'] . ':</th>';
				echo '<td>' . $v . '</td>';
				echo '</tr>';
			}
		}
		echo '</table>';
	}

	// @TODO: Should be in Module?
	final protected function prepare_fields( $fields ) {
		if ( Configure::read( 'layotter_prepare_fields' ) ) {
			foreach ( $this->get_fields() as $field ) {
				if ( $field['type'] == 'image' ) {
					$v                        = new Image( $fields[ $field['name'] ] );
					$fields[ $field['name'] ] = $v;
				}
			}
		}

		return $fields;
	}
}