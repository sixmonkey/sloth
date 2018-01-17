<?php

namespace Sloth\Model;

use Corcel\Model\Taxonomy as Corcel;
use PostTypes\Taxonomy as TaxonomyType;
use Sloth\Model\Post;

class Taxonomy extends Corcel {
	protected $names = [];
	protected $options = [];
	protected $labels = [];
	protected $postTypes = [];
	protected $unique = false;
	protected $taxonomy;

	public function __construct( array $attributes = [] ) {
		if ( $this->taxonomy == null ) {
			$reflection     = new \ReflectionClass( $this );
			$this->taxonomy = strtolower( $reflection->getShortName() );
		}
		if ( is_array( $this->labels ) && count( $this->labels ) ) {
			foreach ( $this->labels as &$label ) {
				$label = __( $label );
			}
		}
		parent::__construct( $attributes );
	}

	public function register() {

		if ( $this->unique ) {
			$this->options['hierarchical']      = false;
			$this->options['parent_item']       = null;
			$this->options['parent_item_colon'] = null;
		}

		$names   = array_merge( $this->names, [ 'name' => $this->getTaxonomy() ] );
		$options = $this->options;
		$labels  = $this->labels;

		$tax = new TaxonomyType( $names, $options, $labels );
		foreach ( $this->postTypes as $postType ) {
			$tax->posttype( $postType );
		}
		$tax->register();
	}

	public function init() {
		if ( $this->unique ) {
			$me = $this;
			foreach ( $this->postTypes as $post_type ) {
				\remove_meta_box( 'tagsdiv-' . $this->getTaxonomy(), $post_type, null );
			}

			$post_types = $this->postTypes;
			add_action( 'add_meta_boxes',
				function () use ( $me, $post_types ) {
					\add_meta_box( 'sloth-taxonomy-' . $me->getTaxonomy(),
						$me->names['singular'],
						[ $me, 'metabox' ],
						$post_types,
						'side' );
				},
				10,
				2 );
		}
	}

	public function metabox( $wp_post ) {
		$tax  = Post::find( $wp_post->ID )->taxonomies()->first();
		$args = [
			'taxonomy'    => $this->getTaxonomy(),
			'hide_empty'  => 0,
			'name'        => 'tax_input[' . $this->getTaxonomy() . '][0]',
			'value_field' => 'slug',
			'selected'    => $tax->slug,
		];
		\wp_dropdown_categories( $args );
	}


	/**
	 * @return string
	 */
	public function getTaxonomy() {
		return $this->taxonomy;
	}
}
