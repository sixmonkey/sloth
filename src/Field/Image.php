<?php
/**
 * User: Kremer
 * Date: 02.02.18
 * Time: 00:29
 */

namespace Sloth\Field;


class Image {
	protected $type;
	protected $url;
	protected $file;
	protected $isResizable = true;
	protected $defaults = [
		'width'   => null,
		'height'  => null,
		'crop'    => null,
		'upscale' => true,
	];

	public function __construct( $url ) {

		if ( is_array( $url ) && isset( $url['url'] ) ) {
			$url = $url['url'];
		}
		if ( is_int( $url ) ) {
			$url = \wp_get_attachment_url( $url );
		}

		$upload_info       = wp_upload_dir();
		$this->url         = $url;
		$this->file        = realpath(
			preg_replace( '#^' . $upload_info['baseurl'] . '#',
				$upload_info['basedir'],
				$this->url )
		);
		$this->isResizable = @is_array( getimagesize( $this->file ) );
	}

	public function resize( $options = [] ) {

		if ( ! $this->isResizable ) {
			return $this->url;
		}


		if ( ! is_array( $options ) ) {
			$args    = func_get_args();
			$options = array_combine(
				array_slice( array_keys( $this->defaults ), 0, count( $args ) ),
				array_slice( $args, 0, count( $this->defaults ) )
			);
		}


		$aq_resize = \Aq_Resize::getInstance();
		$options   = array_merge( $this->defaults, $options );

		return $aq_resize->process( $this->url,
			$options['width'],
			$options['height'],
			$options['crop'],
			true,
			$options['upscale'] );
	}

	public function __toString() {
		return $this->url;
	}
}