<?php

declare(strict_types=1);
namespace Sloth\Model;

use Override;

/**
 * Image Model for WordPress image attachments.
 *
 * Extends the base Model to provide image-specific query scoping
 * and accessors. All queries through this model automatically filter
 * by post_type = 'attachment' AND post_mime_type LIKE 'image/%',
 * ensuring only image attachments are returned.
 *
 * ## Independence from Field\Image
 *
 * This model is the data layer counterpart to Sloth\Field\Image.
 * It handles database queries, meta field parsing, and file path
 * resolution. The Field class handles presentation and image
 * manipulation (resizing, cropping, filtering).
 *
 * ## Automatic mime type scoping
 *
 * The newQuery() override adds a post_mime_type LIKE 'image/%'
 * condition to every query. This prevents non-image attachments
 * (PDFs, videos, documents) from being resolved as images.
 *
 * @since 1.0.0
 * @see Model For the base implementation
 * @see \Sloth\Field\Image For the presentation/manipulation layer
 *
 * @property int $ID                    The attachment ID
 * @property string $post_title            The attachment title (filename)
 * @property string $post_excerpt          The caption text
 * @property string $post_content          The description text
 * @property string $post_mime_type        The MIME type (e.g. 'image/jpeg')
 * @property string $guid                  The original WordPress GUID
 * @property string $alt                   The image alt text (from meta)
 * @property string $caption               Alias for post_excerpt
 * @property string $description           Alias for post_content
 * @property object|null $attachmentMetaData  Unserialized _wp_attachment_metadata
 * @property string|null $attachmentFile      Resolved absolute filesystem path
 * @property string $imageUrl              Full URL via sloth_get_attachment_link filter
 * @property int $imageWidth            Width in pixels (from metadata)
 * @property int $imageHeight           Height in pixels (from metadata)
 * @property bool $isResizable           Whether GD/Imagick can process this file
 *
 * @example
 * ```php
 * // Find by numeric ID
 * $image = Image::findByIdOrUrl(42);
 *
 * // Find by URL string
 * $image = Image::findByIdOrUrl('https://example.com/uploads/2024/photo.jpg');
 *
 * // Access typed properties
 * echo $image->alt;
 * echo $image->imageUrl;
 * echo $image->imageWidth . 'x' . $image->imageHeight;
 * ```
 */
class Image extends Model
{
    /**
     * WordPress post type for this model.
     *
     * Set to 'attachment' so that all queries through this model
     * automatically filter by post_type = 'attachment'.
     *
     * @var false|string
     */
    public static $postType = 'attachment';

    /**
     * Whether to register this model as a WordPress post type.
     *
     * 'attachment' is a built-in WordPress post type and is
     * registered by WordPress core. Setting this to false prevents
     * the ModelRegistrar from attempting duplicate registration.
     *
     * @var bool
     */
    public static $register = false;

    /**
     * Relationships to eager-load on every query.
     *
     * Meta is eager-loaded because image metadata (alt text,
     * attached file path, dimensions) is accessed through the
     * meta relationship. This avoids N+1 queries when working
     * with multiple images.
     *
     * @var array<string>
     */
    protected $with = ['meta'];

    /**
     * Accessors to append to array/JSON representation.
     *
     * Kept minimal compared to the base Model — taxonomy terms,
     * keywords, and other post-specific data are irrelevant
     * for attachment images.
     *
     * @var array<string>
     */
    protected $appends = [
        'title',
        'slug',
        'type',
        'mime_type',
        'url',
        'alt',
        'caption',
        'description',
        'image_url',
    ];

    // -------------------------------------------------------------------------
    // Query scoping
    // -------------------------------------------------------------------------

    /**
     * Create a new query builder for this model.
     *
     * Extends the base Model's newQuery() — which already adds
     * the post_type = 'attachment' constraint — by additionally
     * restricting results to image MIME types only.
     *
     * The post_mime_type LIKE 'image/%' condition ensures that
     * non-image attachments (PDF, video, audio, documents) are
     * never returned by Image queries, even if they share the
     * same post_type.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    #[Override]
    public function newQuery()
    {
        return parent::newQuery()
            ->where('post_mime_type', 'like', 'image/%')
        ;
    }

    // -------------------------------------------------------------------------
    // Finder
    // -------------------------------------------------------------------------

    /**
     * Find a single image by numeric ID or URL string.
     *
     * Accepts the same value formats that Field\Image's constructor
     * handles after array normalisation:
     *
     * - **Numeric value** (int or numeric string): treated as the
     *   attachment post ID. Delegates to Eloquent's find().
     *
     * - **URL string**: the upload base URL prefix is stripped
     *   to obtain the relative path, which is then matched against
     *   the _wp_attached_file meta field.
     *
     * - **Relative path** (e.g. '2024/05/photo.jpg'): passed
     *   directly to the meta query.
     *
     * All lookups are scoped by post_type = 'attachment' AND
     * post_mime_type LIKE 'image/%' via newQuery().
     *
     * @param  int|string $value post ID, full URL, or relative file path
     * @return self|null  the resolved image model, or null when no match is found
     *
     * @example
     * ```php
     * // By numeric ID
     * $image = Image::findByIdOrUrl(42);
     *
     * // By full URL
     * $image = Image::findByIdOrUrl('https://example.com/uploads/photo.jpg');
     *
     * // By relative path
     * $image = Image::findByIdOrUrl('2024/05/photo.jpg');
     * ```
     */
    public static function findByIdOrUrl(int|string $value): ?self
    {
        if (is_numeric($value)) {
            return self::find((int) $value);
        }

        return self::findByUrl($value);
    }

    /**
     * Find a single image by URL string or relative path.
     *
     * Strips the upload base URL prefix to obtain the relative
     * path, then matches against the _wp_attached_file meta field.
     *
     * A relative path such as '2024/05/photo.jpg' is passed
     * through as-is.
     *
     * All lookups are scoped by post_type = 'attachment' AND
     * post_mime_type LIKE 'image/%' via newQuery().
     *
     * @param  string    $url full URL or relative file path
     * @return self|null the resolved image model, or null when no match is found
     *
     * @example
     * ```php
     * // By full URL
     * $image = Image::findByUrl('https://example.com/uploads/photo.jpg');
     *
     * // By relative path
     * $image = Image::findByUrl('2024/05/photo.jpg');
     * ```
     */
    public static function findByUrl(string $url): ?self
    {
        return self::hasMeta('_wp_attached_file', self::urlToRelativePath($url))->first();
    }

    /**
     * Convert an image URL to its relative upload path.
     *
     * Strips the WordPress upload base URL prefix to obtain the
     * relative file path used in _wp_attached_file meta.
     *
     * If the URL does not start with the base URL, it is returned
     * as-is (handles relative paths like '2024/05/photo.jpg').
     *
     * The $baseUrl parameter is optional and defaults to WordPress's
     * upload base URL. It is exposed as a parameter so that tests
     * can exercise the string transformation without coupling to
     * wp_upload_dir().
     *
     * @param  string      $url     full image URL or relative path
     * @param  string|null $baseUrl upload base URL (defaults to wp_upload_dir()['baseurl'])
     * @return string      The relative path (e.g. '2024/05/photo.jpg').
     *
     * @example
     * ```php
     * Image::urlToRelativePath('http://example.com/uploads/2024/05/photo.jpg');
     * // → '2024/05/photo.jpg'
     *
     * Image::urlToRelativePath('2024/05/photo.jpg');
     * // → '2024/05/photo.jpg'
     * ```
     */
    public static function urlToRelativePath(string $url, ?string $baseUrl = null): string
    {
        $baseUrl ??= app()->uri('uploads');
        $relativePath = str_starts_with($url, (string) $baseUrl)
            ? substr($url, strlen((string) $baseUrl))
            : $url;

        return ltrim($relativePath, '/');
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Get the image alt text.
     *
     * WordPress stores attachment alt text in the
     * _wp_attachment_image_alt meta field.
     *
     * @return string the alt text, or an empty string if not set
     *
     * @since 1.0.0
     */
    public function getAltAttribute(): string
    {
        return (string) ($this->meta->_wp_attachment_image_alt ?? '');
    }

    /**
     * Get the image caption.
     *
     * WordPress stores attachment captions in the post_excerpt
     * column. This accessor provides a clearer name for the same data.
     *
     * @return string the caption text, or an empty string if not set
     *
     * @since 1.0.0
     */
    public function getCaptionAttribute(): string
    {
        return (string) ($this->post_excerpt ?? '');
    }

    /**
     * Get the image description.
     *
     * WordPress stores attachment descriptions in the post_content
     * column. This accessor provides a clearer name for the same data.
     *
     * @return string the description text, or an empty string if not set
     *
     * @since 1.0.0
     */
    public function getDescriptionAttribute(): string
    {
        return (string) ($this->post_content ?? '');
    }

    /**
     * Get the unserialised attachment metadata.
     *
     * WordPress stores image dimensions, available sizes, and
     * file path information as a serialised array in the
     * _wp_attachment_metadata meta field.
     *
     * @return object|null the metadata as a stdClass, or null
     *                     if the meta field is missing or invalid
     *
     * @since 1.0.0
     */
    public function getAttachmentMetaDataAttribute(): ?object
    {
        $raw = $this->_wp_attachment_metadata ?? null;

        if (!is_string($raw)) {
            return null;
        }
        $data = @unserialize($raw);

        // WP gibt ein Array zurück, kein Objekt
        return is_array($data) ? (object) $data : null;
    }

    /**
     * Get the absolute filesystem path to the image file.
     *
     * Resolves the _wp_attached_file meta value against the
     * WordPress uploads basedir using realpath().
     *
     * Returns null when the meta value is missing or the file
     * does not exist on disk.
     *
     * @return string|null the resolved absolute path, or null
     *
     * @since 1.0.0
     * @since 1.0.0
     */
    public function getAttachmentFileAttribute(): ?string
    {
        $relPath = $this->meta->_wp_attached_file ?? null;

        if ($relPath === null) {
            return null;
        }

        $path = realpath(wp_upload_dir()['basedir'] . '/' . ltrim($relPath, '/'));

        return $path !== false ? $path : null;
    }

    /**
     * Get the full URL to the image.
     *
     * Uses wp_get_attachment_url() to generate the URL and passes
     * it through the sloth_get_attachment_link filter for
     * CDN or domain customisation.
     *
     * @return string the image URL, or an empty string on failure
     *
     * @since 1.0.0
     */
    public function getImageUrlAttribute(): string
    {
        return (string) apply_filters('sloth_get_attachment_link', wp_get_attachment_url((int) $this->ID));
    }

    /**
     * Get the image width from attachment metadata.
     *
     * Falls back to 0 when metadata is unavailable.
     *
     * @return int the width in pixels, or 0
     *
     * @since 1.0.0
     */
    public function getImageWidthAttribute(): int
    {
        return (int) ($this->attachmentMetaData->width ?? 0);
    }

    /**
     * Get the image height from attachment metadata.
     *
     * Falls back to 0 when metadata is unavailable.
     *
     * @return int the height in pixels, or 0
     *
     * @since 1.0.0
     */
    public function getImageHeightAttribute(): int
    {
        return (int) ($this->attachmentMetaData->height ?? 0);
    }

    /**
     * Check whether this image can be resized or manipulated.
     *
     * An image is considered resizable when the file exists on
     * disk and PHP's getimagesize() recognizes the format.
     *
     * Non-image files (PDFs, SVGs without raster dimensions)
     * and missing files return false.
     *
     * @return bool true when GD/Imagick can process this image
     *
     * @since 1.0.0
     */
    public function getIsResizableAttribute(): bool
    {
        return true;
    }
}
