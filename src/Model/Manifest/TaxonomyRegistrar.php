<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

/**
 * Registers WordPress taxonomies and metaboxes from manifest entries.
 *
 * Reads the pre-computed entry data from TaxonomyManifestBuilder and calls
 * register_extended_taxonomy() for each discovered taxonomy. Also handles
 * removal of default tag-style metaboxes and registration of custom radio
 * metaboxes for unique (single-value) taxonomies.
 *
 * ## Registration flow
 *
 * 1. TaxonomyManifestBuilder discovers Taxonomy subclasses on the `init` hook.
 * 2. Build-time: args, names, slugs, and uniqueness flags are computed.
 * 3. TaxonomyRegistrar reads the cached entries and:
 *    - Registers each taxonomy via register_extended_taxonomy()
 *    - Removes default metaboxes for unique taxonomies
 *    - Adds custom radio metaboxes on the `add_meta_boxes` hook
 *
 * ## Entry data structure
 *
 * Each entry contains:
 * - **slug**: the WordPress taxonomy slug (e.g. 'ort', 'category')
 * - **postTypes**: list of post types this taxonomy applies to
 * - **unique**: whether this is a single-value taxonomy (radio vs. checkbox)
 * - **args**: complete registration args with unique-taxonomy adjustments
 * - **names**: singular and plural labels
 *
 * ## Unique taxonomies
 *
 * When a taxonomy has `$unique = true`, it behaves like a radio button —
 * only one term can be selected per post. The default tag-style metabox
 * (added by WordPress) is removed and replaced with a custom radio metabox
 * rendered by Taxonomy::metabox().
 *
 * @since 1.0.0
 * @see \Sloth\Model\Manifest\TaxonomyManifestBuilder For entry data computation
 * @see \Sloth\Model\Taxonomy                        For the metabox template
 * @see \Sloth\Model\ModelServiceProvider             For hook registration
 */
class TaxonomyRegistrar
{
    /**
     * Creates a new TaxonomyRegistrar instance.
     *
     * @param TaxonomyManifestBuilder $builder The manifest builder that provides
     *                                         the pre-computed entry data.
     * @since 1.0.0
     */
    public function __construct(
        private readonly TaxonomyManifestBuilder $builder,
    ) {}

    /**
     * Register all discovered taxonomies with WordPress.
     *
     * Iterates over the manifest entries and calls register_extended_taxonomy()
     * for each taxonomy. For unique taxonomies, also removes the default
     * tag-style metabox from each associated post type.
     *
     * This method is called on the WordPress `init` hook via
     * ModelServiceProvider::initTaxonomies().
     *
     * Taxonomies with `$register = false` were already excluded at build time.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        foreach ($this->builder->getEntries() as $taxonomyClass => $entry) {
            \register_extended_taxonomy(
                $entry['slug'],
                $entry['postTypes'],
                $entry['args'],
                $entry['names']
            );

            if ($entry['unique']) {
                foreach ($entry['postTypes'] as $postType) {
                    \remove_meta_box('tagsdiv-' . $entry['slug'], $postType, null);
                }
            }
        }
    }

    /**
     * Add custom radio metaboxes for unique taxonomies.
     *
     * Iterates over unique taxonomies and registers a custom metabox
     * that displays radio buttons instead of checkboxes. The metabox
     * template is provided by Taxonomy::metabox().
     *
     * This method is called on the WordPress `add_meta_boxes` hook
     * via ModelServiceProvider.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        foreach ($this->builder->getEntries() as $taxonomyClass => $entry) {
            if (!$entry['unique']) {
                continue;
            }

            $taxonomy = new $taxonomyClass();
            $singular = $entry['names']['singular'] ?? ucfirst($entry['slug']);

            \add_meta_box(
                'sloth-taxonomy-' . $entry['slug'],
                $singular,
                $taxonomy->metabox(...),
                $entry['postTypes'],
                'side'
            );
        }
    }
}
