<?php


namespace Sloth\ACF;


use Sloth\Field\Image;

class ACFHelper extends \Singleton {
	public function __construct() {
		add_action( 'init', [ $this, 'addFilters' ] );
	}

	final public function addFilters() {
		if ( \Configure::read( 'layotter_prepare_fields' ) == 2 ) {
			add_filter( 'acf/format_value/type=image', [ $this, 'load_image' ], 10, 3 );
		}
	}

	final public function load_image( $value, $post_id, $field ) {
		return new Image( (int) $value['ID'] );
	}
}
