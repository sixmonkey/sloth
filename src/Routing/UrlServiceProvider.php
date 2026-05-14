<?php

declare(strict_types=1);
namespace Sloth\Routing;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for relative URL handling.
 *
 * Converts absolute WordPress URLs to root-relative paths when
 * enabled via the app.relative_urls, app.relative_links and
 * app.relative_uploads config keys.
 *
 * @since 1.0.0
 */
class UrlServiceProvider extends ServiceProvider
{
    /**
     * Register the relative URL handler.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton(
            RelativeUrlHandler::class,
            fn ($app): RelativeUrlHandler => new RelativeUrlHandler($app),
        );
    }

    /**
     * Register WordPress filters for relative URL conversion.
     *
     * Filters are only registered when the corresponding config keys
     * are enabled — no unnecessary filter registration.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        $filters = [];

        $relativeLinks = config('app.relative_links', false);
        $relativeUploads = config('app.relative_uploads', false);
        $relativeUrls = config('app.relative_urls', false);

        // app.relative_urls enables both links and uploads
        if ($relativeUrls) {
            $relativeLinks = true;
            $relativeUploads = true;
        }

        if ($relativeLinks) {
            $linkFilters = [
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

            foreach ($linkFilters as $filter) {
                $filters[$filter] = ['callback' => fn ($url) => app(RelativeUrlHandler::class)->toRelativeUrl($url), 'priority' => 90];
            }

            $filters['the_content'] = ['callback' => fn ($content) => app(RelativeUrlHandler::class)->makeHrefsRelative($content), 'priority' => 90];
        }

        if ($relativeUploads) {
            $uploadFilters = [
                'wp_get_attachment_url',
                'template_directory_uri',
                'attachment_link',
                'content_url',
            ];

            foreach ($uploadFilters as $filter) {
                $filters[$filter] = ['callback' => fn ($url) => app(RelativeUrlHandler::class)->toRelativeUrl($url), 'priority' => 90];
            }

            $filters['sloth_get_attachment_link'] = ['callback' => fn ($url) => app(RelativeUrlHandler::class)->toRelativeUrl($url), 'priority' => 90];

            // Merge the_content filter — avoid duplicate registration
            $filters['the_content'] = [
                'callback' => function ($content) {
                    $handler = app(RelativeUrlHandler::class);
                    $content = $handler->makeHrefsRelative($content);

                    return $handler->makeSrcsRelative($content);
                },
                'priority' => 90,
            ];
        }

        return $filters;
    }
}
