<?php

declare(strict_types=1);
namespace Sloth\Routing;

use InvalidArgumentException;
use Sloth\Core\Application;

/**
 * URL Generator.
 *
 * Provides a clean abstraction over WordPress URL functions and integrates
 * with Sloth's Router for named route URL generation.
 *
 * All values are read from the container's `uri.*` bindings — registered
 * during Application::registerBaseUris() — so WordPress functions are
 * never called directly here.
 *
 * ## Usage
 *
 * ```php
 * use Sloth\Facades\URL;
 *
 * URL::home()                                      // https://example.com
 * URL::to('/about')                                // https://example.com/about
 * URL::theme()                                     // https://example.com/wp-content/themes/my-theme
 * URL::theme('css/app.css')                        // https://example.com/.../my-theme/css/app.css
 * URL::asset('css/app.css')                        // https://example.com/.../my-theme/public/css/app.css
 * URL::content()                                   // https://example.com/wp-content
 * URL::uploads()                                   // https://example.com/wp-content/uploads
 * URL::route('post.show', ['slug' => 'hello'])     // https://example.com/posts/hello
 * URL::current()                                   // /current/path
 * URL::full()                                      // https://example.com/current/path
 * ```
 *
 * @since 1.0.0
 */
class UrlGenerator
{
    /**
     * @param Application $app    the application container
     * @param Router      $router the router for named route generation
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly Application $app,
        private readonly Router $router,
    ) {
    }

    /**
     * Get the WordPress home URL.
     *
     * @param string $path optional path to append
     *
     * @since 1.0.0
     */
    public function home(string $path = ''): string
    {
        return $this->app->uri($path, 'home');
    }

    /**
     * Generate an absolute URL for the given path.
     *
     * Alias for home() — mirrors Laravel's URL::to() API.
     *
     * @param string $path path to append to the home URL
     *
     * @since 1.0.0
     */
    public function to(string $path): string
    {
        return $this->home($path);
    }

    /**
     * Get the active theme's directory URI.
     *
     * @param string $path Optional path to append (e.g. 'css/app.css').
     *
     * @since 1.0.0
     */
    public function theme(string $path = ''): string
    {
        return $this->app->uri($path, 'theme');
    }

    /**
     * Get the URL for a theme asset in the public/ directory.
     *
     * Assumes a Vite/webpack build output in theme/public/.
     *
     * @param string $path Asset path relative to public/ (e.g. 'css/app.css').
     *
     * @since 1.0.0
     */
    public function asset(string $path): string
    {
        return $this->theme('public/' . ltrim($path, '/'));
    }

    /**
     * Get the WordPress content directory URI.
     *
     * @param string $path optional path to append
     *
     * @since 1.0.0
     */
    public function content(string $path = ''): string
    {
        return $this->app->uri($path, 'content');
    }

    /**
     * Get the WordPress uploads directory URI.
     *
     * @param string $path optional path to append
     *
     * @since 1.0.0
     */
    public function uploads(string $path = ''): string
    {
        return $this->app->uri($path, 'uploads');
    }

    /**
     * Generate a URL for a named route.
     *
     * The router generates the path and the home URL is prepended.
     *
     * @param string               $name   route name
     * @param array<string, mixed> $params route parameters
     *
     * @throws InvalidArgumentException if the route name does not exist
     *
     * @since 1.0.0
     */
    public function route(string $name, array $params = []): string
    {
        return $this->home($this->router->url($name, $params));
    }

    /**
     * Get the current request path.
     *
     * Returns the REQUEST_URI without query string — just the path portion.
     *
     * @since 1.0.0
     */
    public function current(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    /**
     * Get the full current URL including the home base.
     *
     * @since 1.0.0
     */
    public function full(): string
    {
        return $this->home($this->current());
    }
}
