<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides site information to the Twig context.
 *
 * Available in templates as {{ site.name }}, {{ site.url }}, etc.
 *
 * @since 1.0.0
 */
class SiteContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'site';
    }

    public function resolve(): array
    {
        return [
            'url'           => home_url(),
            'name'          => (string) get_bloginfo('name'),
            'title'         => (string) get_bloginfo('name'),
            'description'   => (string) get_bloginfo('description'),
            'language'      => get_bloginfo('language'),
            'charset'       => get_bloginfo('charset'),
            'admin_email'   => (string) get_bloginfo('admin_email'),
            'canonical_url' => home_url((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            'rdf'           => (string) get_bloginfo('rdf_url'),
            'rss'           => (string) get_bloginfo('rss_url'),
            'rss2'          => (string) get_bloginfo('rss2_url'),
            'atom'          => (string) get_bloginfo('atom_url'),
            'pingback'      => (string) get_bloginfo('pingback_url'),
        ];
    }
}
