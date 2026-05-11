<?php

declare(strict_types=1);
namespace Sloth\Context;

/**
 * Wrapper around WordPress get_bloginfo() for testability.
 *
 * Injects into SiteContextProvider so that provider tests can
 * substitute a mock without needing to patch get_bloginfo() directly,
 * which is defined before Patchwork loads and cannot be redefined.
 *
 * @since 1.0.0
 */
class BlogInfo
{
    /**
     * Get a bloginfo value.
     *
     * Delegates to get_bloginfo() — applies the same filters and escaping.
     *
     * @param string $key the bloginfo key (e.g. 'name', 'description')
     *
     * @since 1.0.0
     */
    public function get(string $key): string
    {
        return (string) get_bloginfo($key);
    }

    /**
     * Get the home URL.
     *
     * @param string $path optional path to append
     *
     * @since 1.0.0
     */
    public function homeUrl(string $path = ''): string
    {
        return home_url($path);
    }
}
