<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

use Sloth\Model\Taxonomy;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for WordPress taxonomy registration.
 *
 * Scans app/Taxonomy/ and theme/Taxonomy/ for Taxonomy subclasses and writes
 * a manifest that registers them via register_extended_taxonomy() on every
 * request — with zero discovery overhead after the first run.
 *
 * Unique (single-value) taxonomies get a custom radio metabox — this is
 * handled in ModelServiceProvider since add_meta_box() must run on the
 * 'add_meta_boxes' hook, not on 'init'.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class TaxonomyManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Taxonomy::class);
    }

    protected function directory(): string
    {
        return 'Taxonomy';
    }

    protected function manifestName(): string
    {
        return 'taxonomies.manifest.php';
    }

    protected function extraLines(string $identifier, string $file): array
    {
        /** @var class-string<Taxonomy> $taxonomyClass */
        $taxonomyClass = $identifier;

        if (!$taxonomyClass::$register) {
            return [];
        }

        $slug  = (new $taxonomyClass())->getTaxonomy();
        $args  = $this->buildArgs($taxonomyClass);
        $names = $this->buildNames($taxonomyClass, $slug);

        $lines = [
            '\register_extended_taxonomy(' . var_export($slug, true) . ', ' . var_export($taxonomyClass::$postTypes, true) . ', ' . var_export($args, true) . ', ' . var_export($names, true) . ');',
        ];

        // Remove default tag-style metabox for unique taxonomies
        if ($taxonomyClass::$unique) {
            foreach ($taxonomyClass::$postTypes as $postType) {
                $lines[] = '\remove_meta_box(' . var_export('tagsdiv-' . $slug, true) . ', ' . var_export($postType, true) . ', null);';
            }
        }

        return $lines;
    }

    protected function bindings(array $map): array
    {
        return [
            'sloth.taxonomies' => collect($map)
                ->mapWithKeys(function ($file, $taxonomyClass) {
                    /** @var class-string<Taxonomy> $taxonomyClass */
                    return [(new $taxonomyClass())->getTaxonomy() => $taxonomyClass];
                })
                ->all(),
        ];
    }

    /**
     * Add custom radio metaboxes for unique taxonomies.
     *
     * Called on the 'add_meta_boxes' hook via ModelServiceProvider.
     *
     * @since 1.0.0
     */
    public function addMetaBoxes(): void
    {
        collect(app()->bound('sloth.taxonomies') ? app('sloth.taxonomies') : [])
            ->filter(fn($taxonomyClass) => $taxonomyClass::$unique)
            ->each(function ($taxonomyClass) {
                /** @var class-string<Taxonomy> $taxonomyClass */
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

    /**
     * @param class-string<Taxonomy> $taxonomyClass
     * @return array<string, mixed>
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
     * @param class-string<Taxonomy> $taxonomyClass
     * @return array{singular: string, plural: string}
     */
    private function buildNames(string $taxonomyClass, string $slug): array
    {
        return [
            'singular' => $taxonomyClass::$names['singular'] ?? ucfirst($slug),
            'plural'   => $taxonomyClass::$names['plural']   ?? ucfirst($slug) . 's',
        ];
    }
}
