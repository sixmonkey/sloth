<?php

namespace Sloth\Model;

use Corcel\Model\Post as Corcel;
use PostTypes\PostType;

class Model extends Corcel {
	protected $names = [];
	protected $options = [];
	protected $labels = [];
	public static $layotter = false;
	protected $register = true;

	public function __construct( array $attributes = [] ) {
		if ( $this->postType == null ) {
			$reflection     = new \ReflectionClass( $this );
			$this->postType = strtolower( $reflection->getShortName() );
		}
		if ( $this->icon == null ) {
			$this->icon = 'admin-post';
		}
		if ( is_array( $this->labels ) && count( $this->labels ) ) {
			foreach ( $this->labels as &$label ) {
				$label = __( $label );
			}
		}
		parent::__construct( $attributes );
	}

	public function register() {
		if ( ! $this->register ) {
			return false;
		}
		$names   = array_merge( $this->names, [ 'name' => $this->getPostType() ] );
		$options = array_merge( $this->options,
			[ 'menu_icon' => 'dashicons-' . preg_replace( '/^dashicons-/', '', $this->icon ) ] );
		$labels  = $this->labels;

		$pt = new PostType( $names, $options, $labels );
		$pt->register();
	}

	/**
	 * @return string
	 */
	public function getPostType() {
		return $this->postType;
	}

	public function getPermalinkAttribute() {
		return \get_permalink( $this->ID );
	}

	final public function init() {
		// fix post_type
		$object = get_post_type_object( $this->postType );
		foreach ( $this->options as $key => $option ) {
			$object->{$key} = $option;
		}
	}

	/**
	 * @return string
	 */
	public function getContentAttribute() {
		$post_content = $this->getAttribute( 'post_content' );
		if ( ! is_null( $post_content ) ) {
			$post_content = \apply_filters( 'the_content', $post_content );
		}

		return (string) $post_content;
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __isset( $key ) {
		return $this->acf->boolean( $key );
	}

	/**
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		$value = parent::__get( $key );

		if ( $value === null && ! property_exists( $this, $key ) ) {
			if ( $this->acf->boolean( $key ) ) {
				return $this->acf->text( $key );
			}
		}

		return $value;
	}
}
