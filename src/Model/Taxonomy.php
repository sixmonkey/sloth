<?php

namespace Sloth\Model;

use Corcel\Model\Taxonomy as Corcel;
use PostTypes\Taxonomy as TaxonomyType;

class Taxonomy extends Corcel {
	protected $names = [];
	protected $options = [];
	protected $labels = [];
	protected $postTypes = [];
	/**
	 * @var string
	 */
	protected $postType;
	public $term_id;

	public function __construct( array $attributes = [] ) {
		if ( $this->postType == null ) {
			$reflection     = new \ReflectionClass( $this );
			$this->postType = strtolower( $reflection->getShortName() );
		}
		if ( is_array( $this->labels ) && count( $this->labels ) ) {
			foreach ( $this->labels as &$label ) {
				$label = __( $label );
			}
		}
		parent::__construct( $attributes );
	}

	public function register() {
		$names   = array_merge( $this->names, [ 'name' => $this->getPostType() ] );
		$options = array_merge( $this->options,
			[ 'menu_icon' => 'dashicons-' . preg_replace( '/^dashicons-/', '', $this->icon ) ] );
		$labels  = $this->labels;

		$tax = new TaxonomyType( $names, $options, $labels );
		foreach ( $this->postTypes as $postType ) {
			$tax->posttype( $postType );
		}
		$tax->register();

	}


	/**
	 * @return string
	 */
	public function getPostType() {
		return $this->postType;
	}
}
