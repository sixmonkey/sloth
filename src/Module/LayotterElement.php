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
		array_shift( $options );

		$keys                          = [
			'class',
			'col_options',
			'row_options',
			'post_options',
			'element_options',
		];
		$fields['_layotter']           = [];
		$fields['_layotter']['passed'] = array_combine( array_intersect_key( $keys, $options ),
			array_intersect_key( $options, $keys ) );


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
					$v = '<img src="' . $fields[ $field['name'] ] . '" width="100"/>';
				} else if ( $field['type'] == 'repeater' ) {
					$v = count( $fields[ $field['name'] ] ) . ' ' . __( 'Elemente', 'sloth' );
				} else if ( is_object( $fields[ $field['name'] ] ) || is_object( $fields[ $field['name'] ] ) || $field['type'] == 'true_false' || $field['type'] == 'taxonomy' ) {
					continue;
				} else if ( $field['type'] == 'image' && $fields[ $field['name'] ]['url'] !== null ) {
					$v = '<img src="' . $fields[ $field['name'] ]['url'] . '" width="100"/>';
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
	final protected function prepare_fields( $values ) {
		$fields = $this->get_fields();
		if ( Configure::read( 'layotter_prepare_fields' ) && $fields) {
			foreach ( $fields as $field ) {
				if ( $field['type'] == 'image' ) {
					$v                        = new Image( $values[ $field['name'] ] );
					$values[ $field['name'] ] = $v;
				}
			}
		}

		return $values;
	}
}
