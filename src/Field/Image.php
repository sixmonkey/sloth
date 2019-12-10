<?php
/**
 * User: Kremer
 * Date: 02.02.18
 * Time: 00:29
 */

namespace Sloth\Field;


use Sloth\Facades\Configure;
use Sloth\Model\Post;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Image as SpatieImage;
use Spatie\Image\Manipulations;

class Image {
    public $url;
    public $alt;
    public $caption;
    public $description;
    protected $post;
    public $sizes = [];

    protected $postID;
    protected $type;
    protected $file;
    protected $isResizable = true;
    protected $metaData;

    protected $defaults = [
        'width'   => null,
        'height'  => null,
        'upscale' => true,
    ];
    protected $attributeTranslations = [
        'caption'     => 'post_excerpt',
        'description' => 'post_content',
        'title'       => 'post_title',
        'alt'         => '_wp_attachment_image_alt',
        'metadata'    => '_wp_attachment_metadata',
    ];

    /**
     * Image constructor.
     *
     * @param       $url
     * @param array $sizes
     */
    public function __construct( $url ) {

        if ( is_null( $url ) ) {
            $this->url = null;

            return;
        }

        if ( is_array( $url ) && isset( $url['url'] ) ) {
            $url = $url['url'];
        }

        if ( (int) $url ) {
            $this->post = Post::find( $url );
            $url        = is_object( $this->post ) ? $this->post->url : $this->post['url'];
        } else {
            $this->post = Post::where( 'guid', 'like', str_replace( WP_CONTENT_URL, '%', $url ) )->first();
        }

        if ( is_object( $this->post ) ) {
            $this->alt         = $this->post->meta->_wp_attachment_image_alt;
            $this->caption     = $this->post->post_excerpt;
            $this->description = $this->post->post_content;

            $this->postID   = $this->post->ID;
            $this->metaData = unserialize( $this->meta->_wp_attachment_metadata );

            $this->url  = apply_filters( 'sloth_get_attachment_link', $url );
            $this->file = realpath( WP_CONTENT_DIR . DS . 'uploads' . DS . $this->post->meta->_wp_attached_file );

            $this->isResizable = @is_array( getimagesize( $this->file ) );

            $this->sizes = $this->sizes();
        } else {
            $this->isResizable = false;
        }
    }

    /**
     * @param $size
     *
     * @return array|mixed|string
     */
    public function getThemeSized( $size ) {
        if ( is_array( $size ) ) {
            $size = reset( $size );
        }
        if ( isset( $this->sizes[ $size ] ) ) {
            return $this->sizes[ $size ];
        }

        $image_sizes = Configure::read( 'theme.image-sizes' );

        if ( isset( $image_sizes[ $size ] ) ) {
            return $this->resize( $image_sizes[ $size ] );
        }

        return $this->resize();
    }

    /**
     * @param array $options
     *
     * @return array|mixed|string
     */
    public function resize( $options = [] ) {

        if ( ! $this->isResizable || $this->url == null ) {
            return $this->url;
        }
        if ( ! is_array( $options ) ) {
            $args    = func_get_args();
            $options = array_combine(
                array_slice( array_keys( $this->defaults ), 0, count( $args ) ),
                array_slice( $args, 0, count( $this->defaults ) )
            );
        }


        if ( ! isset( $options['height'] ) ) {
            $ratio             = $this->metaData['width'] / $options['width'];
            $height            = round( $this->metaData['height'] / $ratio );
            $options['height'] = $height;
        }

        $options = $this->processOptions( $options );

        $sheerFileName = $this->getFilename( $options );

        SlothMediaVersion::updateOrCreate( [
            'post_excerpt' => json_encode( $options ),
            'guid'         => $this->getUrl( $sheerFileName, false ),
            'post_parent'  => $this->post->ID,
        ] );

        return $this->getUrl( $sheerFileName );
    }


    /**
     * @param array $options
     *
     * @return mixed|string
     */
    protected function getFilename( $options = [] ) {
        $upload_info = wp_upload_dir();
        $upload_dir  = realpath( $upload_info['basedir'] );

        $suffix = "{$options['width']}x{$options['height']}";

        unset( $options['width'], $options['height'] );

        $options_named = [];
        foreach ( $options as $method => $values ) {
            if ( is_array( $values ) ) {
                $values = implode( '-', $values );
            }
            $name = $method;
            if ( ! is_bool( $values ) ) {
                $name .= '-' . $values;
            }
            $options_named[] = $name;
        }
        $options_named[] = $suffix;

        $suffix = implode( '-', $options_named );

        // Get image info.
        $info = pathinfo( $this->file );
        $ext  = $info['extension'];

        $dst_rel_path = str_replace( '.' . $ext, '', $this->file );
        $dst_rel_path = str_replace( $upload_dir, '', $dst_rel_path );
        $dst_rel_path = "{$dst_rel_path}-{$suffix}.{$ext}";

        return $dst_rel_path;
    }

    /**
     * @param $filename
     *
     * @return string
     */
    protected function getAbsoluteFilename( $filename ) {
        $upload_info = wp_upload_dir();
        $upload_dir  = realpath( $upload_info['basedir'] );

        return $upload_dir . $filename;
    }

    /**
     * @param      $filename
     * @param bool $full
     *
     * @return string
     */
    protected function getUrl( $filename, $full = null ) {
        $upload_info = wp_upload_dir();

        $baseurl    = rtrim( apply_filters( 'sloth_get_attachment_link', $upload_info['baseurl'] ), '/' );
        $upload_url = $baseurl . '/' . ltrim( $filename, '/' );

        return $upload_url;
    }

    /**
     * @param $options
     *
     * @return array
     */
    protected function processOptions( $options ) {
        $options = array_merge( $this->defaults, $options );
        # keep downward compatibility
        unset( $options['upscale'] );
        ksort( $options );

        $output = [];
        foreach ( $options as $method => $values ) {
            if ( is_numeric( $method ) && is_string( $values ) && is_bool( $values ) ) {
                $method = $values;
                $values = true;
            }
            $output[ $method ] = $values;
        }

        return $output;
    }

    public function __toString() {
        return (string) $this->url;
    }

    /**
     * @param $what
     *
     * @return mixed
     */
    public function __get( $what ) {
        if ( $what === 'sizes' ) {
            return $this->sizes();
        }

        if ( isset( $this->attributeTranslations[ $what ] ) ) {
            $what = $this->attributeTranslations[ $what ];
        }

        $v = $this->post->{$what};

        return $v;
    }

    /**
     * @param $what
     *
     * @return bool
     */
    public function __isset( $what ) {

        if ( isset( $this->attributeTranslations[ $what ] ) ) {
            $what = $this->attributeTranslations[ $what ];
        }

        $v = $this->post->{$what};

        return $v != null;
    }

    /**
     * @return array
     */
    public function sizes() {
        $imageSizes = Configure::read( 'theme.image-sizes' );
        $sizes      = [];

        if ( is_array( $imageSizes ) ) {
            foreach ( $imageSizes as $size => $option ) {
                $sizes[ $size ] = $this->getThemeSized( $size );
            }
        }

        return $sizes;
    }

}
