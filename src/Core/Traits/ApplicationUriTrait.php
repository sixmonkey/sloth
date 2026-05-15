<?php

declare(strict_types=1);
namespace Sloth\Core\Traits;

/**
 * Manages all URI resolution for the application.
 *
 * Extracted from ApplicationPathTrait to separate URI concerns from
 * filesystem path management. This trait provides:
 *
 * - URI registration (registerBaseUris)
 * - URI accessor (uri)
 *
 * URIs are stored in the container under the `uri.*` prefix.
 *
 * @since 2.0.0
 */
trait ApplicationUriTrait
{
    // -------------------------------------------------------------------------
    // URI registration
    // -------------------------------------------------------------------------

    /**
     * Register all base URIs for the application.
     *
     * Called during boot() after WordPress is available. URIs are stored
     * in the container under the `uri.*` prefix and accessible via uri().
     * Trailing slashes are stripped for consistency.
     *
     * Registered container keys:
     * - `uri.home`    — WordPress home URL (home_url('/'))
     * - `uri.theme`   — Active theme directory URI
     * - `uri.content` — WordPress content directory URI
     * - `uri.uploads` — WordPress uploads directory URI
     *
     * @since 1.0.0
     */
    protected function registerBaseUris(): void
    {
        if (!function_exists('home_url')) {
            return;
        }

        $this->addUri('home', home_url('/'));
        $this->addUri('theme', get_template_directory_uri());
        $this->addUri('content', content_url());
        $this->addUri('uploads', wp_upload_dir()['baseurl']);
    }

    /**
     * Add a URI to the container.
     *
     * Trailing slashes are stripped so callers can safely append paths
     * with or without a leading slash.
     *
     * @param string $key container key (stored as "uri.{$key}")
     * @param string $uri absolute URI
     *
     * @since 1.0.0
     */
    public function addUri(string $key, string $uri): void
    {
        $this->instance('uri.' . $key, rtrim($uri, '/'));
    }

    // -------------------------------------------------------------------------
    // URI accessor
    // -------------------------------------------------------------------------

    /**
     * Get a registered URI from the container.
     *
     * @param string $path   optional path to append (leading slash is stripped)
     * @param string $prefix URI key — see registerBaseUris() for available keys
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     *
     * @since 1.0.0
     */
    public function uri(string $path = '', string $prefix = 'home'): string
    {
        $base = $this->get('uri.' . $prefix);

        return $path !== '' && $path !== '0'
            ? $base . '/' . ltrim($path, '/')
            : $base;
    }
}
