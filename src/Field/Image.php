<?php

declare(strict_types=1);
namespace Sloth\Field;

use AllowDynamicProperties;
use Illuminate\Contracts\Container\BindingResolutionException;
use Override;
use Sloth\Model\Image as ImageModel;
use Sloth\Model\SlothMediaVersion;
use Stringable;

/**
 * Image field wrapper with manipulation capabilities.
 *
 * @since 1.0.0
 */
#[AllowDynamicProperties]
class Image implements Stringable
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
     * The underlying image model.
     *
     * @since 1.0.0
     */
    protected ?ImageModel $post = null;

    /**
     * Available image sizes.
     *
     * @since 1.0.0
     *
     * @var array<string, string>
     */
    public array $sizes = [];

    /**
     * The height of this image.
     *
     * @since 1.0.0
     */
    public int $height = 0;

    /**
     * The width of this image.
     *
     * @since 1.0.0
     */
    public int $width = 0;

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
     *
     * @var object<string, mixed>|null
     */
    protected ?object $metaData = null;

    /**
     * Default options for image manipulation.
     *
     * @since 1.0.0
     *
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
     *
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
     * @param array<string, mixed>|int|string|null $url URL, array with 'url'/'ID' key, or (numeric) ID
     *
     * @throws BindingResolutionException
     *
     * @since 1.0.0
     */
    public function __construct(mixed $url = null)
    {
        $url = self::normaliseInput($url);

        if ($url === null) {
            $this->isResizable = false;

            return;
        }

        $this->post = ImageModel::findByIdOrUrl($url);

        if ($this->post === null) {
            $this->isResizable = false;

            return;
        }

        $this->alt = $this->post->alt;
        $this->caption = $this->post->caption;
        $this->description = $this->post->description;
        $this->postID = (int) $this->post->ID;
        $this->metaData = $this->post->attachmentMetaData;
        $this->width = $this->post->imageWidth;
        $this->height = $this->post->imageHeight;
        $this->url = $this->post->imageUrl;
        $this->file = $this->post->attachmentFile;
        $this->isResizable = $this->post->isResizable;
        $this->sizes = $this->sizes();
    }

    /**
     * Normalise constructor input to a value that ImageModel::findByIdOrUrl can resolve.
     *
     * @param  mixed           $url raw constructor argument
     * @return int|string|null normalised ID, URL string, or null when unresolvable
     *
     * @since 1.0.0
     */
    private static function normaliseInput(mixed $url): int|string|null
    {
        return match (true) {
            $url === null, $url === false        => null,
            is_array($url) && isset($url['ID'])  => (int) $url['ID'],
            is_array($url) && isset($url['url']) => $url['url'],
            is_array($url)                       => null,
            default                              => $url,
        };
    }

    /**
     * Get a theme-sized image.
     *
     * Looks up the size in theme.image-sizes config and resizes accordingly.
     * Falls back to resize() without options when the size is not configured.
     *
     * @param array<string>|string $size size name or array of dimensions
     *
     * @throws BindingResolutionException
     *
     * @since 1.0.0
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
     * Resize the image with the given options.
     *
     * Stores a SlothMediaVersion record for the resized image so that
     * the media version processor can generate the actual file.
     *
     * @param array<string, mixed> ...$options Resize options (width, height, etc.)
     *
     * @since 1.0.0
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
                array_slice($args, 0, count($this->defaults)),
            );
        }

        if (!isset($options['height']) && isset($this->metaData->width, $this->metaData->height)) {
            $ratio = $this->metaData->width / $options['width'];
            $options['height'] = (int) round($this->metaData->height / $ratio);
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
     * Builds the destination filename by appending a suffix that encodes
     * the manipulation options (e.g. 'image-800x600-crop-center.jpg').
     *
     * @param array<string, mixed> $options manipulation options
     *
     * @since 1.0.0
     */
    protected function getFilename(array $options = []): string
    {
        if ($this->file === null) {
            return '';
        }

        $uploadDir = realpath(app()->path('uploads'));
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
     * Get the absolute filesystem path for a relative upload filename.
     *
     * @param string $filename Relative filename (e.g. '/2026/05/image-800x600.jpg').
     *
     * @throws BindingResolutionException
     *
     * @since 1.0.0
     */
    protected function getAbsoluteFilename(string $filename): string
    {
        $uploadDir = realpath(app()->path('uploads'));

        return ($uploadDir !== false ? $uploadDir : '') . $filename;
    }

    /**
     * Get the URL for an upload file.
     *
     * Passes the base upload URI through the `sloth_get_attachment_link`
     * filter so themes can substitute a CDN URL.
     *
     * @param string    $filename relative filename
     * @param bool|null $full     whether to return a full URL (default: true)
     *
     * @since 1.0.0
     */
    protected function getUrl(string $filename, ?bool $full = true): string
    {
        $baseUrl = rtrim(
            (string) apply_filters('sloth_get_attachment_link', app()->uri('uploads')),
            '/',
        );

        return $baseUrl . '/' . ltrim($filename, '/');
    }

    /**
     * Process and normalise manipulation options.
     *
     * Merges with defaults, removes upscale, sorts keys alphabetically
     * so the option suffix is deterministic.
     *
     * @param  array<string, mixed> $options raw manipulation options
     * @return array<string, mixed> processed options
     *
     * @since 1.0.0
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
     * Convert to string — returns the image URL.
     *
     * @since 1.0.0
     */
    #[Override]
    public function __toString(): string
    {
        return (string) $this->url;
    }

    /**
     * Get a dynamic property.
     *
     * Translates common aliases (caption, description, title, alt, metadata)
     * to their underlying WordPress column/meta names before lookup.
     *
     * @param string $what property name
     *
     * @throws BindingResolutionException
     *
     * @since 1.0.0
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
     * Check if a dynamic property is set.
     *
     * @param string $what property name
     *
     * @since 1.0.0
     */
    public function __isset(string $what): bool
    {
        if ($this->post === null) {
            return false;
        }

        if (isset($this->attributeTranslations[$what])) {
            $what = $this->attributeTranslations[$what];
        }

        return ($this->post->{$what} ?? null) != null;
    }

    /**
     * Get all available theme-defined image sizes.
     *
     * Iterates over theme.image-sizes config and returns a map
     * of size name to resized URL.
     *
     * @throws BindingResolutionException
     *
     * @return array<string, string>
     *
     * @since 1.0.0
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
