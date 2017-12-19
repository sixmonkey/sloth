<?php

namespace Sloth\Model;

use Corcel\Model\Post as Corcel;
use PostTypes\PostType;

class Model extends Corcel {
	protected $names = [];
	protected $options = [];

	public function __construct( array $attributes = [] ) {
		if ( $this->postType == null ) {
			$reflection = new \ReflectionClass($this);
			$this->postType = strtolower($reflection->getShortName());
		}
		if ( $this->icon == null ) {
			$this->icon = 'admin-post';
		}
		parent::__construct( $attributes );
	}

	public function register() {
		$names = array_merge( $this->names, [ 'name' => $this->getPostType() ] );
		$options = array_merge( $this->options,
			[ 'menu_icon' => 'dashicons-' . preg_replace( '/^dashicons-/', '', $this->icon ) ] );

		new PostType( $names, $options );
	}
}
