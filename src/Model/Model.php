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
}
