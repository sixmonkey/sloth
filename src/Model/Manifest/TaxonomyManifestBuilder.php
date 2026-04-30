<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

use Sloth\Model\Taxonomy;
use Sloth\Support\Manifest\PathBasedManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for WordPress taxonomy registration.
 *
 * Scans app/Taxonomy/ and theme/Taxonomy/ for Taxonomy subclasses and writes
 * a manifest that includes all discovered files and provides pre-computed
 * registration arguments via its return value.
 *
 * ## Discovery
 *
 * Uses ClassMapFinder to locate all non-abstract classes extending
 * Sloth\Model\Taxonomy. Each discovered class is inspected for its static
 * properties ($register, $options, $names, $postTypes, $unique).
 *
 * ## Build-time computation
 *
 * The expensive work — building registration args, label arrays, slug
 * resolution — happens once at build time and is cached in the manifest file.
 * At runtime, TaxonomyRegistrar reads this data and calls
 * register_extended_taxonomy() directly.
 *
 * ## Entry data structure
 *
 * ```php
 * [
 *     '\\App\\Taxonomy\\OrtTaxonomy' => [
 *         'slug'      => 'ort',
 *         'postTypes' => ['event'],
 *         'unique'    => true,
 *         'args'      => ['hierarchical' => false, ...],
 *         'names'     => ['singular' => 'Ort', 'plural' => 'Orte'],
 *     ],
 * ]
 * ```
 *
 * Taxonomies with `$register = false` are excluded from the entry data.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\PathBasedManifestBuilder For the base class lifecycle
 * @see \Sloth\Model\Manifest\TaxonomyRegistrar         For runtime registration
 */
class TaxonomyManifestBuilder extends PathBasedManifestBuilder
{
    /**
     * Return the finder for Taxonomy subclass discovery.
     *
     * Uses ClassMapFinder filtered to classes extending Sloth\Model\Taxonomy.
     * Non-abstract subclasses are included; abstract base classes are excluded.
     *
     * @return FinderInterface The configured ClassMapFinder.
     * @since 1.0.0
     */
    #[\Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Taxonomy::class);
    }

    /**
     * Return the subdirectory name for Taxonomy files.
     *
     * Scans `app/Taxonomy/` and `theme/Taxonomy/`.
     *
     * @return string Always 'Taxonomy'.
     * @since 1.0.0
     */
    #[\Override]
    protected function directory(): string
    {
        return 'Taxonomy';
    }

    /**
     * Compute registration entry data for all discovered taxonomies.
     *
     * Iterates over each discovered Taxonomy class and builds the full set of
     * arguments needed by register_extended_taxonomy(). Taxonomies with
     * `$register = false` are skipped.
     *
     * Unique (single-value) taxonomies are flagged so the Registrar can
     * remove the default tag-style metabox and register a custom radio
     * metabox instead.
     *
     * @param array<string, string> $map Taxonomy class name => absolute file path.
     * @return array<string, array{slug: string, postTypes: list<string>, unique: bool, args: array<string, mixed>, names: array<string, string>}>
     * @since 1.0.0
     */
    #[\Override]
    protected function entries(array $map): array
    {
        $entries = [];

        /** @var class-string<Taxonomy> $taxonomyClass */
        foreach ($map as $taxonomyClass => $file) {
            if (!$taxonomyClass::$register) {
                continue;
            }

            $slug = (new $taxonomyClass())->getTaxonomy();

            $entries[$taxonomyClass] = [
                'slug' => $slug,
                'postTypes' => $taxonomyClass::$postTypes,
                'unique' => $taxonomyClass::$unique,
                'args' => $this->buildArgs($taxonomyClass),
                'names' => $this->buildNames($taxonomyClass, $slug),
            ];
        }

        return $entries;
    }

    /**
     * Build the extended-cpts registration args for a taxonomy.
     *
     * Merges the taxonomy's static $options with computed values:
     * - For unique taxonomies: sets hierarchical to false and clears
     *   parent item labels (unique taxonomies don't have hierarchy).
     *
     * @param class-string<Taxonomy> $taxonomyClass The taxonomy class.
     * @return array<string, mixed>                 The complete args array.
     * @since 1.0.0
     */
    private function buildArgs(string $taxonomyClass): array
    {
        $args = $taxonomyClass::$options;

        if ($taxonomyClass::$unique) {
            $args['hierarchical']      = false;
            $args['parent_item']       = null;
            $args['parent_item_colon'] = null;
        }

        return $args;
    }

    /**
     * Build the display names array for a taxonomy.
     *
     * Falls back to auto-generated names from the taxonomy slug when
     * $names is not defined on the taxonomy class.
     *
     * @param class-string<Taxonomy> $taxonomyClass The taxonomy class.
     * @param string                 $slug          The taxonomy slug.
     * @return array{singular: string, plural: string}
     * @since 1.0.0
     */
    private function buildNames(string $taxonomyClass, string $slug): array
    {
        return [
            'singular' => $taxonomyClass::$names['singular'] ?? ucfirst($slug),
            'plural'   => $taxonomyClass::$names['plural']   ?? ucfirst($slug) . 's',
        ];
    }
}
