<?php

declare(strict_types=1);

namespace Sloth\Field;

use Sloth\Facades\Configure;
use Sloth\Model\Post;
use Sloth\Model\SlothMediaVersion;
use Spatie\Image\Image as SpatieImage;

/**
 * Image field wrapper with manipulation capabilities.
 *
 * @since 1.0.0
 */
class Image
{
    /**
     * Image URL.
     *
     * @since 1.0.0
     * @var string|null
     */
    public ?string $url = null;

    /**
     * Image alt text.
     *
     * @since 1.0.0
     * @var string|null
     */
    public ?string $alt = null;

    /**
     * Image caption.
     *
     * @since 1.0.0
     * @var string|null
     */
    public ?string $caption = null;

    /**
     * Image description.
     *
     * @since 1.0.0
     * @var string|null
     */
    public ?string $description = null;

    /**
     * Post object.
     *
     * @since 1.0.0
     * @var Post|array<string, mixed>|null
     */
    protected Post|array|null $post = null;

    /**
     * Available image sizes.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    public array $sizes = [];

    /**
     * Post ID.
     *
     * @since 1.0.0
     * @var int|null
     */
    protected ?int $postID = null;

    /**
     * Image type.
     *
     * @since 1.0.0
     * @var string|null
     */
    protected ?string $type = null;

    /**
     * Image file path.
     *
     * @since 1.0.0
     * @var string|null
     */
    protected ?string $file = null;

    /**
     * Whether the image is resizable.
     *
     * @since 1.0.0
     * @var bool
     */
    protected bool $isResizable = true;

    /**
     * Image metadata.
     *
     * @since 1.0.0
     * @var array<string, mixed>|null
     */
    protected ?array $metaData = null;

    /**
     * Default options for image manipulation.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected array $defaults = [
        'width'   => null,
        'height'  => null,
        'upscale' => true,
    ];

    /**
     * Attribute translation mapping.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $attributeTranslations = [
        'caption'     => 'post_excerpt',
        'description' => 'post_content',
        'title'       => 'post_title',
        'alt'         => '_wp_attachment_image_alt',
        'metadata'    => '_wp_attachment_metadata',
    ];

    /**
     * Image constructor.
     *
     * @since 1.0.0
     *
     * @param int|array<string, mixed>|null $url URL, array with 'url' key, or attachment ID
     */
    public function __construct(int|array|null $url = null)
    {
        if ($url === null) {
            $this->url = null;

            return;
        }

        if (is_array($url) && isset($url['url'])) {
            $url = $url['url'];
        }

        if ((int) $url) {
            $this->post = Post::find($url);
            $url = is_object($this->post) ? $this->post->url : ($this->post['url'] ?? null);
        } else {
            $this->post = Post::where('guid', 'like', str_replace(WP_CONTENT_URL, '%', (string) $url))->first();
        }

        if (is_object($this->post)) {
            $this->alt = $this->post->meta->_wp_attachment_image_alt;
            $this->caption = $this->post->post_excerpt;
            $this->description = $this->post->post_content;

            $this->postID = (int) $this->post->ID;
            $this->metaData = unserialize($this->post->meta->_wp_attachment_metadata ?? '');

            $this->url = (string) apply_filters('sloth_get_attachment_link', (string) $url);
            $path = realpath(WP_CONTENT_DIR . DS . 'uploads' . DS . $this->post->meta->_wp_attached_file);
            $this->file = $path !== false ? $path : null;

            if ($this->file) {
                $this->isResizable = @is_array(getimagesize($this->file));
            }

            $this->sizes = $this->sizes();
        } else {
            $this->isResizable = false;
        }
    }

    /**
     * Get a theme-sized image.
     *
     * @since 1.0.0
     *
     * @param string|array<string> $size Size name or array of dimensions
     *
     * @return string
     */
    public function getThemeSized(string|array $size): string
    {
        if (is_array($size)) {
            $size = (string) reset($size);
        }
        if (isset($this->sizes[$size])) {
            return $this->sizes[$size];
        }

        $imageSizes = Configure::read('theme.image-sizes');

        if (isset($imageSizes[$size])) {
            return $this->resize($imageSizes[$size]);
        }

        return $this->resize();
    }

    /**
     * Resize the image with options.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> ...$options Resize options or width
     *
     * @return string
     */
    public function resize(...$options): string
    {
        if (!$this->isResizable || $this->url == null) {
            return (string) $this->url;
        }

        $args = func_get_args();
        $options = is_array($args[0] ?? null) ? $args[0] : [];

        if (!is_array($options)) {
            $options = array_combine(
                array_slice(array_keys($this->defaults), 0, count($args)),
                array_slice($args, 0, count($this->defaults))
            );
        }

        if (!isset($options['height'])) {
            $ratio = $this->metaData['width'] / $options['width'];
            $options['height'] = (int) round($this->metaData['height'] / $ratio);
        }

        $options = $this->processOptions($options);

        $sheerFileName = $this->getFilename($options);

        SlothMediaVersion::updateOrCreate([
            'post_excerpt' => json_encode($options),
            'guid'         => $this->getUrl($sheerFileName, false),
            'post_parent'  => $this->post->ID,
        ]);

        return $this->getUrl($sheerFileName);
    }

    /**
     * Get the filename for a manipulated image.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $options Manipulation options
     *
     * @return string
     */
    protected function getFilename(array $options = []): string
    {
        if ($this->file === null) {
            return '';
        }

        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        $suffix = "{$options['width']}x{$options['height']}";

        unset($options['width'], $options['height']);

        $optionsNamed = [];
        foreach ($options as $method => $values) {
            if (is_array($values)) {
                $values = implode('-', $values);
            }
            $name = $method;
            if (!is_bool($values)) {
                $name .= '-' . $values;
            }
            $optionsNamed[] = $name;
        }
        $optionsNamed[] = $suffix;

        $suffix = implode('-', $optionsNamed);

        $info = pathinfo($this->file);
        $ext = $info['extension'] ?? '';

        $dstRelPath = str_replace('.' . $ext, '', $this->file);
        $dstRelPath = str_replace((string) $uploadDir, '', $dstRelPath);
        $dstRelPath = "{$dstRelPath}-{$suffix}.{$ext}";

        return $dstRelPath;
    }

    /**
     * Get the absolute file path.
     *
     * @since 1.0.0
     *
     * @param string $filename Relative filename
     *
     * @return string
     */
    protected function getAbsoluteFilename(string $filename): string
    {
        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        return $uploadDir . $filename;
    }

    /**
     * Get the URL for a file.
     *
     * @since 1.0.0
     *
     * @param string      $filename Relative filename
     * @param bool|null $full     Whether to include full URL (default: true)
     *
     * @return string
     */
    protected function getUrl(string $filename, ?bool $full = true): string
    {
        $uploadInfo = wp_upload_dir();

        $baseUrl = rtrim((string) apply_filters('sloth_get_attachment_link', $uploadInfo['baseurl']), '/');

        return $baseUrl . '/' . ltrim($filename, '/');
    }

    /**
     * Process manipulation options.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $options Manipulation options
     *
     * @return array<string, mixed>
     */
    protected function processOptions(array $options): array
    {
        $options = array_merge($this->defaults, $options);
        unset($options['upscale']);
        ksort($options);

        $output = [];
        foreach ($options as $method => $values) {
            if (is_numeric($method) && is_string($values) && is_bool($values)) {
                $method = $values;
                $values = true;
            }
            $output[$method] = $values;
        }

        return $output;
    }

    /**
     * Convert to string (returns URL).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->url;
    }

    /**
     * Get a dynamic property.
     *
     * @since 1.0.0
     *
     * @param string $what Property name
     *
     * @return mixed
     */
    public function __get(string $what): mixed
    {
        if ($what === 'sizes') {
            return $this->sizes();
        }

        if (isset($this->attributeTranslations[$what])) {
            $what = $this->attributeTranslations[$what];
        }

        return $this->post->{$what};
    }

    /**
     * Check if a property is set.
     *
     * @since 1.0.0
     *
     * @param string $what Property name
     *
     * @return bool
     */
    public function __isset(string $what): bool
    {
        if (isset($this->attributeTranslations[$what])) {
            $what = $this->attributeTranslations[$what];
        }

        $v = $this->post->{$what};

        return $v != null;
    }

    /**
     * Get all available sizes.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function sizes(): array
    {
        $imageSizes = Configure::read('theme.image-sizes');
        $sizes = [];

        if (is_array($imageSizes)) {
            foreach ($imageSizes as $size => $option) {
                $sizes[$size] = $this->getThemeSized($size);
            }
        }

        return $sizes;
    }
}
