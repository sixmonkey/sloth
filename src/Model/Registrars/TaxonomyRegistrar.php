<?php

declare(strict_types=1);

namespace Sloth\Model\Registrars;

use Sloth\Core\Application;
use Sloth\Core\ServiceProvider;
use Sloth\Model\Resolvers\TaxonomiesResolver;

/**
 * Service provider for taxonomy registration and initialization.
 *
 * Handles:
 * - Discovery and registration of taxonomy classes from DIR_APP/Taxonomy/
 * - WordPress taxonomy registration via register_taxonomy()
 * - Storing taxonomies in container as 'sloth.taxonomies'
 * - Metabox customization for unique (non-hierarchical) taxonomies
 *
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 * @see \Sloth\Plugin\Plugin
 */
class TaxonomyRegistrar
{
    /**
     * Registered taxonomies mapping.
     *
     * @var array<string, string>
     */
    protected array $taxonomies = [];

    /**
     * Application instance.
     *
     * @var Application|null
     */
    protected ?Application $app;

    /**
     * Constructor.
     */
    public function init(): void
    {
        $this->app = app();
        $this->registerTaxonomies();
        $this->registerMetaboxes();
    }

    /**
     * Register taxonomies with WordPress.
     *
     * @since 1.0.0
     */
    protected function registerTaxonomies(): void
    {
        $taxonomies = [];
        TaxonomiesResolver::resolve()->each(function (string $taxonomyClass) use (&$taxonomies) {
            $taxonomy = new $taxonomyClass();
            \register_taxonomy(
                $taxonomy->getTaxonomy(),
                $taxonomy->getPostTypes(),
                $taxonomy->getRegistrationArgs()
            );
            $taxonomies[$taxonomy->getTaxonomy()] = $taxonomyClass;
        });

        $this->taxonomies = $taxonomies;
        $this->app['sloth.taxonomies'] = $this->taxonomies;
    }

    /**
     * Register metaboxes for unique taxonomies.
     *
     * @since 1.0.0
     */
    protected function registerMetaboxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!$taxonomy->unique) {
                continue;
            }

            foreach ($taxonomy->postTypes as $postType) {
                \remove_meta_box('tagsdiv-' . $taxonomy->getTaxonomy(), $postType, null);
            }
        }
    }

    /**
     * Add metaboxes for unique taxonomies.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        foreach ($this->taxonomies as $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!$taxonomy->unique) {
                continue;
            }

            $postTypes = $taxonomy->postTypes;

            \add_meta_box(
                'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                $taxonomy->names['singular'],
                $taxonomy->metabox(...),
                $postTypes,
                'side'
            );
        }
    }

    /**
     * Get all registered taxonomies.
     *
     * @return array<string, string> Taxonomy slug to class name mapping
     * @since 1.0.0
     *
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }
}
