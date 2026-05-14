<?php

declare(strict_types=1);
namespace Sloth\Routing;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\Application;

/**
 * Handles conversion of absolute WordPress URLs to root-relative paths.
 *
 * @since 1.0.0
 * @see UrlServiceProvider
 */
class RelativeUrlHandler
{
    public function __construct(protected Application $app)
    {
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
     * Convert href attributes in HTML content to relative paths.
     *
     * @param string $content HTML content with href attributes
     *
     * @throws BindingResolutionException
     *
     * @return string Content with relative hrefs
     *
     * @since 1.0.0
     */
    public function makeHrefsRelative(string $content): string
    {
        return str_replace('href="' . rtrim($this->app->uri(), '/'), 'href="', $content);
    }

    /**
     * Convert src attributes in HTML content to relative paths.
     *
     * @param string $content HTML content with src attributes
     *
     * @throws BindingResolutionException
     *
     * @return string Content with relative srcs
     *
     * @since 1.0.0
     */
    public function makeSrcsRelative(string $content): string
    {
        return str_replace('src="' . rtrim($this->app->uri(), '/'), 'src="', $content);
    }
}
