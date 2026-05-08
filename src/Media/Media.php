<?php

declare(strict_types=1);
namespace Sloth\Media;

use BitAndBlack\ImageInformation\Image as ImageInformation;
use BitAndBlack\ImageInformation\Source\File;
use function add_image_size;

/**
 * Media handling utilities for WordPress.
 *
 * Provides functionality for:
 * - Custom image sizes registration
 * - SVG mime type support
 * - Converting absolute URLs to root-relative paths
 *
 * @since 1.0.0
 * @see MediaServiceProvider
 */
class Media
{
    /**
     * Add SVG mime type.
     *
     * @param  array<string, string> $mimes
     * @return array<string, string>
     *
     * @since 1.0.0
     */
    public function addSvgMime(array $mimes): array
    {
        $mimes['svg'] = 'image/svg+xml';

        return $mimes;
    }

    /**
     * Fix missing or incorrect image dimensions in attachment metadata.
     *
     * WordPress stores image dimensions in _wp_attachment_metadata during
     * upload. This filter ensures width and height are always correct by
     * reading the actual file via ImageInformation when the metadata
     * contains zero or missing values.
     *
     * @param  array<string, mixed> $metadata     The generated attachment metadata.
     * @param  int                  $attachmentId The attachment post ID.
     * @return array<string, mixed> The corrected metadata.
     *
     * @since 1.0.0
     */
    public function fixAttachmentDimensions(array $metadata, int $attachmentId): array
    {
        if (isset($metadata['width'], $metadata['height']) && $metadata['width'] > 0 && $metadata['height'] > 0) {
            return $metadata;
        }

        $file = get_attached_file($attachmentId);

        if ($file === false || !file_exists($file)) {
            return $metadata;
        }

        $size = (new ImageInformation(new File($file)))->getSize();

        $metadata['width'] = $size['width'];
        $metadata['height'] = $size['height'];

        return $metadata;
    }

    /**
     * Register custom image sizes from config.
     *
     * @since 1.0.0
     */
    public function registerImageSizes(): void
    {
        $imageSizes = config('theme.image-sizes');

        if ($imageSizes && is_array($imageSizes)) {
            foreach ($imageSizes as $name => $options) {
                $options = array_merge([
                    'width'   => 800,
                    'height'  => 600,
                    'crop'    => false,
                    'upscale' => false,
                ], $options);
                add_image_size($name, $options['width'], $options['height'], $options['crop']);
            }
        }

        if (config('urls.relative')) {
            $this->makeUploadsRelative();
            $this->makeLinksRelative();
        }

        if (config('links.urls.relative')) {
            $this->makeLinksRelative();
        }

        if (config('uploads.urls.relative')) {
            $this->makeUploadsRelative();
        }
    }

    /**
     * Convert all links to root-relative URLs.
     *
     * @since 1.0.0
     * @see toRelativeUrl() For the URL transformation
     */
    public function makeLinksRelative(): void
    {
        $filters = [
            'day_link',
            'year_link',
            'post_link',
            'page_link',
            'term_link',
            'month_link',
            'search_link',
            'the_permalink',
            'get_shortlink',
            'post_type_link',
            'get_pagenum_link',
            'post_type_archive_link',
            'get_comments_pagenum_link',
            'sloth_get_permalink',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, $this->toRelativeUrl(...), 90, 1);
        }

        add_filter('the_content', $this->makeHrefsRelative(...), 90, 1);
    }

    /**
     * Convert all upload URLs to root-relative.
     *
     * @since 1.0.0
     * @see toRelativeUrl() For the URL transformation
     */
    public function makeUploadsRelative(): void
    {
        $filters = [
            'wp_get_attachment_url',
            'template_directory_uri',
            'attachment_link',
            'content_url',
        ];

        foreach ($filters as $filter) {
            add_filter($filter, $this->toRelativeUrl(...), 90, 1);
        }

        add_filter('sloth_get_attachment_link', $this->toRelativeUrl(...), 90, 1);
        add_filter('the_content', $this->makeSrcsRelative(...), 90, 1);
    }

    /**
     * Convert a URL to a root-relative path.
     *
     * @param  string $url The full URL to convert
     * @return string The relative path
     *
     * @since 1.0.0
     */
    public function toRelativeUrl(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /**
     * Convert href attributes in content to relative paths.
     *
     * @param  string $content HTML content with href attributes
     * @return string Content with relative hrefs
     *
     * @since 1.0.0
     */
    public function makeHrefsRelative(string $content): string
    {
        return str_replace('href="' . rtrim(home_url(), '/'), 'href="', $content);
    }

    /**
     * Convert src attributes in content to relative paths.
     *
     * @param  string $content HTML content with src attributes
     * @return string Content with relative srcs
     *
     * @since 1.0.0
     */
    public function makeSrcsRelative(string $content): string
    {
        return str_replace('src="' . rtrim(home_url(), '/'), 'src="', $content);
    }
}
