<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides the current taxonomy term to the Twig context.
 *
 * Available in templates as {{ taxonomy }} and {{ {taxonomy_slug} }}.
 * Only active on taxonomy archive pages.
 *
 * @since 1.0.0
 */
class TaxonomyContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'taxonomy';
    }

    #[\Override]
    public function shouldResolve(): bool
    {
        return is_tax();
    }

    public function resolve(): mixed
    {
        global $taxonomy;
        $taxonomies = app('sloth.taxonomies') ?? [];
        $taxonomyClass = $taxonomies[$taxonomy] ?? \Sloth\Model\Taxonomy::class;

        return call_user_func([$taxonomyClass, 'find'], [get_queried_object()->term_id])->first();
    }
}
