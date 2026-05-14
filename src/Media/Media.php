<?php

declare(strict_types=1);
namespace Sloth\Media;

use function add_image_size;
use Sloth\Core\Application;

/**
 * Media handling utilities for WordPress.
 *
 * Handles:
 * - Custom image sizes registration from config
 * - SVG mime type support for media uploads
 *
 * Relative URL handling has moved to Sloth\Routing\UrlServiceProvider.
 *
 * @since 1.0.0
 * @see MediaServiceProvider
 */
class Media
{
    public function __construct(protected Application $app)
    {
    }

    /**
     * Add SVG mime type to allowed upload types.
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
     * Register custom image sizes from config.
     *
     * Reads image sizes from config('theme.image_sizes') and registers
     * them with WordPress via add_image_size().
     *
     * @since 1.0.0
     */
    public function registerImageSizes(): void
    {
        $imageSizes = config('theme.image_sizes', []);

        if (!$imageSizes || !is_array($imageSizes)) {
            return;
        }

        foreach ($imageSizes as $name => $options) {
            $options = array_merge([
                'width'  => 800,
                'height' => 600,
                'crop'   => false,
            ], $options);

            add_image_size($name, $options['width'], $options['height'], $options['crop']);
        }
    }
}
