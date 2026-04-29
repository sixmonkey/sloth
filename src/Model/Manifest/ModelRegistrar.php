<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

/**
 * Registers WordPress post types from manifest entries.
 *
 * Reads the pre-computed entry data from ModelManifestBuilder and calls
 * register_extended_post_type() for each discovered model. No discovery
 * or argument-building overhead occurs at runtime — the manifest file
 * (cached by Opcache) provides all necessary data.
 *
 * ## Registration flow
 *
 * 1. ModelManifestBuilder discovers Model subclasses on the `init` hook.
 * 2. Build-time: args, names, and admin_cols are computed and cached.
 * 3. ModelRegistrar reads the cached entries and registers each post type
 *    with WordPress via register_extended_post_type().
 *
 * ## Entry data structure
 *
 * Each entry contains:
 * - **postType**: the WordPress post type slug (e.g. 'news', 'event')
 * - **args**: complete registration args including menu_icon and admin_cols
 * - **names**: singular, plural, and slug labels
 *
 * ## Design notes
 *
 * This class is intentionally thin — all the expensive computation happens
 * in ModelManifestBuilder at build time. The Registrar's job is purely
 * to call the WordPress API with pre-computed data.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Manifest\ModelManifestBuilder For entry data computation
 * @see \Sloth\Model\ModelServiceProvider            For hook registration
 */
class ModelRegistrar
{
    /**
     * Creates a new ModelRegistrar instance.
     *
     * @param ModelManifestBuilder $builder The manifest builder that provides
     *                                      the pre-computed entry data.
     * @since 1.0.0
     */
    public function __construct(
        private readonly ModelManifestBuilder $builder,
    ) {}

    /**
     * Register all discovered post types with WordPress.
     *
     * Iterates over the manifest entries and calls register_extended_post_type()
     * for each model. This method is called on the WordPress `init` hook
     * via ModelServiceProvider::initModels().
     *
     * Models with `$register = false` were already excluded at build time
     * and will not appear in the entries.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        foreach ($this->builder->getEntries() as $modelClass => $entry) {
            \register_extended_post_type(
                $entry['postType'],
                $entry['args'],
                $entry['names']
            );
        }
    }
}
