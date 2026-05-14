<?php

declare(strict_types=1);
namespace Sloth\Media;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for media handling.
 *
 * Registers image sizes and SVG mime type support.
 * Relative URL handling is managed by UrlServiceProvider.
 *
 * @since 1.0.0
 */
class MediaServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton('media', fn (): Media => new Media($this->app));
    }

    /**
     * Register media hooks.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHooks(): array
    {
        return [
            'init' => ['callback' => fn () => app('media')->registerImageSizes(), 'priority' => 20],
        ];
    }

    /**
     * Register media filters.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        return [
            'upload_mimes' => fn (array $mimes) => app('media')->addSvgMime($mimes),
        ];
    }
}
