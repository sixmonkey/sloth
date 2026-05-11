<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\BlogInfo;
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
    public function __construct(private readonly BlogInfo $blogInfo) {}

    public function key(): string
    {
        return 'site';
    }

    public function resolve(): array
    {
        return [
            'url'           => $this->blogInfo->homeUrl(),
            'name'          => $this->blogInfo->get('name'),
            'title'         => $this->blogInfo->get('name'),
            'description'   => $this->blogInfo->get('description'),
            'language'      => $this->blogInfo->get('language'),
            'charset'       => $this->blogInfo->get('charset'),
            'admin_email'   => $this->blogInfo->get('admin_email'),
            'canonical_url' => $this->blogInfo->homeUrl((string) ($_SERVER['REQUEST_URI'] ?? '/')),
            'rdf'           => $this->blogInfo->get('rdf_url'),
            'rss'           => $this->blogInfo->get('rss_url'),
            'rss2'          => $this->blogInfo->get('rss2_url'),
            'atom'          => $this->blogInfo->get('atom_url'),
            'pingback'      => $this->blogInfo->get('pingback_url'),
        ];
    }
}
