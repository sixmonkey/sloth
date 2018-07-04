<?php
/**
 * User: Kremer
 * Date: 02.02.18
 * Time: 00:29
 */

namespace Sloth\Field;


use Sloth\Facades\Configure;

class Image {
	protected $type;
	public $url;
	protected $file;
	protected $isResizable = true;
	public $alt;
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
		global $wpdb;
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ) );
		$att_id     = $attachment[0];

		$this->alt = get_post_meta( $att_id, '_wp_attachment_image_alt', true );

		$upload_info       = wp_upload_dir();
		$this->url         = $url;
		$this->file        = realpath(
			preg_replace( '#^' . $upload_info['baseurl'] . '#',
				$upload_info['basedir'],
				$this->url )
		);
		$this->isResizable = @is_array( getimagesize( $this->file ) );
	}

	public function getThemeSized( $size ) {
		$image_sizes = Configure::read( 'theme.image-sizes' );
		if ( isset( $image_sizes[ $size ] ) ) {
			return $this->resize( $image_sizes[ $size ] );
		}

		return $this->resize();
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
		$options     = array_merge( $this->defaults, $options );
		$upload_info = wp_upload_dir();
		$upload_dir  = realpath( $upload_info['basedir'] );
		$upload_url  = $upload_info['baseurl'];


		if ( ! file_exists( $this->file ) or ! getimagesize( $this->file ) ) {
			throw new \Exception( 'Image file does not exist (or is not an image): ' . $this->file );
		}

		// Get image info.
		$info = pathinfo( $this->file );
		$ext  = $info['extension'];
		list( $orig_w, $orig_h ) = getimagesize( $this->file );

		if ( true === $options['upscale'] ) add_filter( 'image_resize_dimensions', array($this, 'upscale'), 10, 6 );


		// Get image size after cropping.
		$dims  = image_resize_dimensions( $orig_w, $orig_h, $options['width'], $options['height'], $options['crop'] );
		$dst_w = $dims[4];
		$dst_h = $dims[5];

		// Return the original image only if it exactly fits the needed measures.
		if ( ! $dims || ( ( ( null === $options['height'] && $orig_w == $options['width'] ) xor ( null === $options['width'] && $orig_h == $options['height'] ) ) xor ( $options['height'] == $orig_h && $options['width'] == $orig_w ) ) ) {
			$img_url = $this->url;
			$dst_w   = $orig_w;
			$dst_h   = $orig_h;
		} else {
			// Use this to check if cropped image already exists, so we can return that instead.
			$suffix       = "{$dst_w}x{$dst_h}";
			$dst_rel_path = str_replace( '.' . $ext, '', $this->file );
			$destfilename = "{$upload_dir}{$dst_rel_path}-{$suffix}.{$ext}";

			if ( ! $dims || ( true == $options['crop'] && false == $options['upscale'] && ( $dst_w < $options['width'] || $dst_h < $options['height'] ) ) ) {
				// Can't resize, so return false saying that the action to do could not be processed as planned.
				throw new \Exception( 'Unable to resize image because image_resize_dimensions() failed' );
			} // Else check if cache exists.
			else if ( file_exists( $destfilename ) && getimagesize( $destfilename ) ) {
				$img_url = "{$upload_url}{$dst_rel_path}-{$suffix}.{$ext}";
			} // Else, we resize the image and return the new resized image url.
			else {

				$editor = \wp_get_image_editor( $this->file );

				if ( is_wp_error( $editor ) || is_wp_error( $editor->resize( $options['width'],
						$options['height'],
						$options['crop'] ) ) ) {
					throw new \Exception( 'Unable to get WP_Image_Editor: ' .
					                      $editor->get_error_message() . ' (is GD or ImageMagick installed?)' );
				}

				$resized_file = $editor->save();

				if ( ! is_wp_error( $resized_file ) ) {
					$resized_rel_path = str_replace( $upload_dir, '', $resized_file['path'] );
					$img_url          = $upload_url . $resized_rel_path;
				} else {
					throw new Aq_\Exception( 'Unable to save resized image file: ' . $editor->get_error_message() );
				}

			}
		}

		// Okay, leave the ship.
		if ( true === $options['upscale'] ) {
			remove_filter( 'image_resize_dimensions', [ $this, 'upscale' ] );
		}


		return $img_url;
	}

	/**
	 * Callback to overwrite WP computing of thumbnail measures
	 */
	function upscale( $default, $orig_w, $orig_h, $dest_w, $dest_h, $crop ) {
		if ( ! $crop ) {
			return null;
		} // Let the wordpress default function handle this.

		// Here is the point we allow to use larger image size than the original one.
		$aspect_ratio = $orig_w / $orig_h;
		$new_w        = $dest_w;
		$new_h        = $dest_h;

		if ( ! $new_w ) {
			$new_w = intval( $new_h * $aspect_ratio );
		}

		if ( ! $new_h ) {
			$new_h = intval( $new_w / $aspect_ratio );
		}

		$size_ratio = max( $new_w / $orig_w, $new_h / $orig_h );

		$crop_w = round( $new_w / $size_ratio );
		$crop_h = round( $new_h / $size_ratio );

		$s_x = floor( ( $orig_w - $crop_w ) / 2 );
		$s_y = floor( ( $orig_h - $crop_h ) / 2 );

		return [
			0,
			0,
			(int) $s_x,
			(int) $s_y,
			(int) $new_w,
			(int) $new_h,
			(int) $crop_w,
			(int) $crop_h,
		];
	}

	public function __toString() {
		return (string) $this->url;
	}
}