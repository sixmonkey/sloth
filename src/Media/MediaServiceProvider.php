<?php

declare(strict_types=1);

namespace Sloth\Media;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for media and URL handling.
 *
 * Handles:
 * - Custom image sizes registration
 * - SVG mime type for media uploads
 * - Converting absolute URLs to root-relative paths
 *
 * @since 1.0.0
 * @see \Sloth\Plugin\Plugin
 */
class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('media', fn() => new Media());
    }

    /**
     * Register media hooks and filters.
     *
     * @since 1.0.0
     */
    public function getHooks(): array
    {
        return [
            'init' => ['callback' => fn() => app('media')->registerImageSizes(), 'priority' => 20],
        ];
    }

    /**
     * Register media filters.
     *
     * @since 1.0.0
     */
    public function getFilters(): array
    {
        $filters = [
            'upload_mimes' => fn(array $mimes) => app('media')->addSvgMime($mimes),
        ];

        if (config('urls.relative')) {
            $filters['the_content'] = fn(string $c) => app('media')->makeHrefsRelative($c);
        }

        return $filters;
    }
}
