<?php

declare(strict_types=1);
namespace Sloth\Media;

use Override;
use Sloth\Core\ServiceProvider;
use Sloth\Event\WpHookFired;

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
    #[Override]
    public function register(): void
    {
        $this->app->singleton('media', fn (): Media => new Media());
    }

    /**
     * Boot the media service provider.
     *
     * Registers the content filter for relative URL conversion
     * via the WordPress Event Bridge, so that other listeners
     * can also manipulate the_content in a decoupled manner.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if (config('urls.relative')) {
            $this->app->make('events')->listen('wp:the_content', function (WpHookFired $event): void {
                $event->result = app('media')->makeHrefsRelative($event->result);
            });
        }
    }

    /**
     * Register media hooks and filters.
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
            'upload_mimes'                => fn (array $mimes) => app('media')->addSvgMime($mimes),
            'wp_generate_attachment_metadata' => fn (array $metadata, int $attachmentId) => app('media')->fixAttachmentDimensions($metadata, $attachmentId),
        ];
    }
}
