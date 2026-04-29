<?php

declare(strict_types=1);

namespace Sloth\Field;

use BitAndBlack\ImageInformation\Exception\ExtensionNotSupportedException;
use BitAndBlack\ImageInformation\Source\File;
use BitAndBlack\ImageInformation\Image as ImageInformation;
use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Facades\Cache;
use Sloth\Model\Post;
use Sloth\Model\SlothMediaVersion;

/**
 * Image field wrapper with manipulation capabilities.
 *
 * @since 1.0.0
 */
#[\AllowDynamicProperties]
class Image implements \Stringable
{
    /**
     * Image URL.
     *
     * @since 1.0.0
     */
    public ?string $url = null;

    /**
     * Image alt text.
     *
     * @since 1.0.0
     */
    public ?string $alt = null;

    /**
     * Image caption.
     *
     * @since 1.0.0
     */
    public ?string $caption = null;

    /**
     * Image description.
     *
     * @since 1.0.0
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
     */
    protected ?int $postID = null;

    /**
     * Image type.
     *
     * @since 1.0.0
     */
    protected ?string $type = null;

    /**
     * Image file path.
     *
     * @since 1.0.0
     */
    protected ?string $file = null;

    /**
     * Whether the image is resizable.
     *
     * @since 1.0.0
     */
    protected bool $isResizable = true;

    /**
     * Image metadata.
     *
     * @since 1.0.0
     * @var object<string, mixed>|null
     */
    protected ?object $metaData = null;

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
     * @param int|array<string, mixed>|null $url URL, array with 'url' key, or attachment ID
     * @throws BindingResolutionException
     * @throws ExtensionNotSupportedException
     * @since 1.0.0
     *
     */
    public function __construct(int|array|false|string|null $url = null)
    {
        if ($url === null || $url === false) {
            $this->url = null;

            return;
        }

        if (is_array($url) && isset($url['url'])) {
            $url = $url['url'];
        }

        if ((int) $url !== 0) {
            $this->post = Post::find($url);
            $url = is_object($this->post) ? $this->post->url : ($this->post['url'] ?? null);
        } else {
            $this->post = Post::where('guid', 'like', str_replace(WP_CONTENT_URL, '%', (string) $url))->first();
        }

        if (is_object($this->post)) {
            $this->alt = $this->post->meta->_wp_attachment_image_alt ?? null;
            $this->caption = $this->post->post_excerpt ?? null;
            $this->description = $this->post->post_content ?? null;

            $this->postID = (int)$this->post->ID;
            $metadata = $this->post->_wp_attachment_metadata ?? null;
            $this->metaData = is_string($metadata) ? (object)@unserialize($metadata) : null;

            $this->width = $this->metaData->width;
            $this->height = $this->metaData->height;

            $this->url = (string)apply_filters('sloth_get_attachment_link', (string)($url ?? ''));
            $path = realpath(WP_CONTENT_DIR . '/' . 'uploads' . '/' . ($this->post->meta->_wp_attached_file ?? ''));
            $this->file = $path !== false ? $path : null;

            if ($this->file !== null) {
                $this->isResizable = @is_array(getimagesize($this->file));
            }

            if ($this->file) {
                $file = $this->file;
                $size = Cache::rememberForever('sloth.media.size' . md5($this->file), function () use ($file) {
                    $image = new ImageInformation(new File($file));
                    return $image->getSize();
                });
                $this->width = $size['width'];
                $this->height = $size['height'];
            }

            $this->sizes = $this->sizes();
        } else {
            $this->isResizable = false;
        }
    }

    /**
     * Get a theme-sized image.
     *
     * @param string|array<string> $size Size name or array of dimensions
     * @throws BindingResolutionException
     * @since 1.0.0
     *
     */
    public function getThemeSized(string|array $size): string
    {
        if (is_array($size)) {
            $size = (string) reset($size);
        }

        if (isset($this->sizes[$size])) {
            return $this->sizes[$size];
        }

        $imageSizes = config('theme.image-sizes');

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
     */
    public function resize(...$options): string
    {
        if (!$this->isResizable || $this->url === null || $this->file === null) {
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

        if (!isset($options['height']) && isset($this->metaData->width, $this->metaData->height)) {
            $ratio = $this->metaData->width / $options['width'];
            $options['height'] = (int)round($this->metaData->height / $ratio);
        }

        $options = $this->processOptions($options);

        $sheerFileName = $this->getFilename($options);

        SlothMediaVersion::updateOrCreate([
            'guid'        => $this->getUrl($sheerFileName, false),
            'post_parent' => $this->post->ID,
        ], [
            'post_excerpt' => json_encode($options),
        ]);

        return $this->getUrl($sheerFileName);
    }

    /**
     * Get the filename for a manipulated image.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $options Manipulation options
     */
    protected function getFilename(array $options = []): string
    {
        if ($this->file === null) {
            return '';
        }

        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        $suffix = sprintf('%sx%s', $options['width'], $options['height']);

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

        return sprintf('%s-%s.%s', $dstRelPath, $suffix, $ext);
    }

    /**
     * Get the absolute file path.
     *
     * @since 1.0.0
     *
     * @param string $filename Relative filename
     */
    protected function getAbsoluteFilename(string $filename): string
    {
        $uploadInfo = wp_upload_dir();
        $uploadDir = realpath($uploadInfo['basedir']);

        return ($uploadDir !== false ? $uploadDir : '') . $filename;
    }

    /**
     * Get the URL for a file.
     *
     * @since 1.0.0
     *
     * @param string      $filename Relative filename
     * @param bool|null $full     Whether to include full URL (default: true)
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
     */
    #[\Override]
    public function __toString(): string
    {
        return (string) $this->url;
    }

    /**
     * Get a dynamic property.
     *
     * @param string $what Property name
     * @throws BindingResolutionException
     * @since 1.0.0
     *
     */
    public function __get(string $what): mixed
    {
        if ($what === 'sizes') {
            return $this->sizes();
        }

        if (isset($this->attributeTranslations[$what])) {
            $what = $this->attributeTranslations[$what];
        }

        if ($this->post === null) {
            return null;
        }

        return $this->post->{$what} ?? null;
    }

    /**
     * Check if a property is set.
     *
     * @since 1.0.0
     *
     * @param string $what Property name
     */
    public function __isset(string $what): bool
    {
        if ($this->post === null) {
            return false;
        }

        if (isset($this->attributeTranslations[$what])) {
            $what = $this->attributeTranslations[$what];
        }

        $v = $this->post->{$what} ?? null;

        return $v != null;
    }

    /**
     * Get all available sizes.
     *
     * @return array<string, string>
     * @throws BindingResolutionException
     * @since 1.0.0
     *
     */
    public function sizes(): array
    {
        $imageSizes = config('theme.image-sizes');
        $sizes = [];

        if (is_array($imageSizes)) {
            foreach (array_keys($imageSizes) as $size) {
                $sizes[$size] = $this->getThemeSized($size);
            }
        }

        return $sizes;
    }
}
