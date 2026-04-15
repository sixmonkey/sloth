<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Facades\Configure;

/**
 * Service provider for media and URL handling.
 *
 * Handles:
 * - Custom image sizes registration
 * - SVG mime type for media uploads
 * - Converting absolute URLs to root-relative paths
 *
 * ## Image Sizes
 *
 * Register custom image sizes via config('theme.image-sizes'):
 * ```php
 * Configure::write('theme.image-sizes', [
 *     'thumbnail' => ['width' => 300, 'height' => 200, 'crop' => true],
 *     'hero' => ['width' => 1200, 'height' => 600],
 * ]);
 * ```
 *
 * ## Relative URLs
 *
 * When enabled via config, converts absolute URLs to root-relative:
 * - `urls.relative` - Enable both links and uploads relative
 * - `links.urls.relative` - Only convert post/term links
 * - `uploads.urls.relative` - Only convert media upload URLs
 *
 * This is useful for local development or when serving from multiple domains.
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class MediaServiceProvider
{
    /**
     * Register media hooks and filters.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        add_action('init', $this->registerImageSizes(...), 20);

        add_filter('upload_mimes', function (array $mimes): array {
            $mimes['svg'] = 'image/svg+xml';

            return $mimes;
        });

        if (Configure::read('urls.relative')) {
            $this->makeUploadsRelative();
            $this->makeLinksRelative();
        }

        if (Configure::read('links.urls.relative')) {
            $this->makeLinksRelative();
        }

        if (Configure::read('uploads.urls.relative')) {
            $this->makeUploadsRelative();
        }
    }

    /**
     * Register custom image sizes from config.
     *
     * Reads image size definitions from config('theme.image-sizes') and
     * registers them with WordPress using add_image_size().
     *
     * Each size can define:
     * - width (default: 800)
     * - height (default: 600)
     * - crop (default: false)
     * - upscale (default: false)
     *
     * @since 1.0.0
     */
    public function registerImageSizes(): void
    {
        $imageSizes = Configure::read('theme.image-sizes');
        if ($imageSizes && is_array($imageSizes)) {
            foreach ($imageSizes as $name => $options) {
                $options = array_merge([
                    'width' => 800,
                    'height' => 600,
                    'crop' => false,
                    'upscale' => false,
                ], $options);
                \add_image_size($name, $options['width'], $options['height'], $options['crop']);
            }
        }
    }

    /**
     * Convert all links to root-relative URLs.
     *
     * Registers filters on WordPress permalink functions to strip the
     * domain from URLs, making them root-relative (e.g., /about instead
     * of https://example.com/about).
     *
     * Filters applied to: post links, page links, term links, archive links,
     * comment pagination, and content href attributes.
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
     * Registers filters on WordPress upload URL functions to strip the
     * domain from media URLs. This enables serving uploads from relative paths.
     *
     * Filters applied to: attachment URLs, template directory URI,
     * attachment links, and content src attributes.
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
     * Strips the domain and scheme from a URL, returning only the path.
     * This is the core function used by makeLinksRelative() and
     * makeUploadsRelative() to convert absolute URLs to relative paths.
     *
     * @since 1.0.0
     *
     * @param string $url The full URL to convert
     * @return string The relative path (e.g., /about or /wp-content/uploads/image.jpg)
     */
    public function toRelativeUrl(string $url): string
    {
        return (string) parse_url($url, PHP_URL_PATH);
    }

    /**
     * Convert href attributes in content to relative paths.
     *
     * Processes all href attributes in HTML content and removes the home URL,
     * converting absolute links to relative paths.
     *
     * @since 1.0.0
     *
     * @param string $content HTML content with href attributes
     * @return string Content with relative hrefs
     */
    public function makeHrefsRelative(string $content): string
    {
        return str_replace('href="' . rtrim((string) WP_HOME, '/'), 'href="', $content);
    }

    /**
     * Convert src attributes in content to relative paths.
     *
     * Processes all src attributes in HTML content and removes the home URL,
     * converting absolute media URLs to relative paths.
     *
     * @since 1.0.0
     *
     * @param string $content HTML content with src attributes
     * @return string Content with relative srcs
     */
    public function makeSrcsRelative(string $content): string
    {
        return str_replace('src="' . rtrim((string) WP_HOME, '/'), 'src="' . rtrim((string) WP_HOME, '/'), $content);
    }
}
