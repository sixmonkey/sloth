<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

use Sloth\Model\Taxonomy;

/**
 * Registers metaboxes for unique (single-value) taxonomies.
 *
 * Reads the sloth.taxonomies container binding populated by TaxonomyManifestBuilder
 * and adds custom radio metaboxes on the add_meta_boxes hook.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Manifest\TaxonomyManifestBuilder
 */
class TaxonomyMetaBoxRegistrar
{
    /**
     * Add custom radio metaboxes for unique taxonomies.
     *
     * Called on the 'add_meta_boxes' hook via ModelServiceProvider.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        $taxonomies = app()->bound('sloth.taxonomies') ? app('sloth.taxonomies') : [];

        collect($taxonomies)
            ->filter(fn($taxonomyClass) => $taxonomyClass::$unique)
            ->each(function ($taxonomyClass) {
                $taxonomy = new $taxonomyClass();
                $singular = $taxonomyClass::$names['singular'] ?? ucfirst($taxonomy->getTaxonomy());

                \add_meta_box(
                    'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                    $singular,
                    $taxonomy->metabox(...),
                    $taxonomyClass::$postTypes,
                    'side'
                );
            });
    }
}
