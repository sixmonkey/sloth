<?php

declare(strict_types=1);

namespace Sloth\Model\Manifest;

use Illuminate\Support\Str;
use Sloth\Model\Model;
use Sloth\Model\Proxy\CurrentModelProxy;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;

/**
 * Builds a manifest for WordPress post type registration.
 *
 * Scans app/Model/ and theme/Model/ for Model subclasses and writes a manifest
 * that includes all discovered files and provides pre-computed registration
 * arguments via its return value.
 *
 * ## Discovery
 *
 * Uses ClassMapFinder to locate all non-abstract classes extending
 * Sloth\Model\Model. Each discovered class is inspected for its static
 * properties ($register, $options, $names, $icon, $admin_columns).
 *
 * ## Build-time computation
 *
 * The expensive work — building registration args, label arrays, admin column
 * definitions — happens once at build time and is cached in the manifest file.
 * At runtime, ModelRegistrar reads this data and calls
 * register_extended_post_type() directly.
 *
 * ## Entry data structure
 *
 * ```php
 * [
 *     '\\App\\Model\\NewsModel' => [
 *         'postType' => 'news',
 *         'args'     => ['public' => true, 'show_in_rest' => true, ...],
 *         'names'    => ['singular' => 'News', 'plural' => 'News', 'slug' => 'news'],
 *     ],
 * ]
 * ```
 *
 * Models with `$register = false` are excluded from the entry data.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder For the base class lifecycle
 * @see \Sloth\Model\Manifest\ModelRegistrar            For runtime registration
 */
class ModelManifestBuilder extends AbstractManifestBuilder
{
    /**
     * Return the finder for Model subclass discovery.
     *
     * Uses ClassMapFinder filtered to classes extending Sloth\Model\Model.
     * Non-abstract subclasses are included; abstract base classes are excluded.
     *
     * @return FinderInterface The configured ClassMapFinder.
     * @since 1.0.0
     */
    #[\Override]
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Model::class);
    }

    /**
     * Return the subdirectory name for Model files.
     *
     * Scans `app/Model/` and `theme/Model/`.
     *
     * @return string Always 'Model'.
     * @since 1.0.0
     */
    #[\Override]
    protected function directory(): string
    {
        return 'Model';
    }

    /**
     * Return the manifest filename.
     *
     * @return string Always 'models.manifest.php'.
     * @since 1.0.0
     */
    #[\Override]
    protected function manifestName(): string
    {
        return 'models.manifest.php';
    }

    /**
     * Compute registration entry data for all discovered models.
     *
     * Iterates over each discovered Model class and builds the full set of
     * arguments needed by register_extended_post_type(). Models with
     * `$register = false` are skipped.
     *
     * The computed data includes:
     * - postType: the WordPress post type slug
     * - args: merged registration args with admin_cols
     * - names: singular, plural, and slug labels
     *
     * @param array<string, string> $map Model class name => absolute file path.
     * @return array<string, array{postType: string, args: array<string, mixed>, names: array<string, string>}>
     * @since 1.0.0
     */
    #[\Override]
    protected function entries(array $map): array
    {
        $entries = [];

        /** @var class-string<Model> $modelClass */
        foreach ($map as $modelClass => $file) {
            if (!$modelClass::$register) {
                continue;
            }

            $postType = $modelClass::getPostType();

            $entries[$modelClass] = [
                'postType' => $postType,
                'args' => $this->buildArgs($modelClass),
                'names' => $this->buildNames($modelClass, $postType),
            ];
        }

        return $entries;
    }

    /**
     * Build the extended-cpts registration args for a model.
     *
     * Merges the model's static $options with computed values:
     * - menu_icon: normalizes the $icon property to dashicons-* format
     * - admin_cols: translates $admin_columns to extended-cpts format
     *
     * @param class-string<Model> $modelClass The model class to build args for.
     * @return array<string, mixed>            The complete args array.
     * @since 1.0.0
     */
    private function buildArgs(string $modelClass): array
    {
        $args = $modelClass::$options;

        if ($modelClass::$icon !== null) {
            $args['menu_icon'] = 'dashicons-' . preg_replace('/^dashicons-/', '', $modelClass::$icon);
        }

        $args['admin_cols'] = $this->buildAdminCols($modelClass);

        return $args;
    }

    /**
     * Build the display names array for a model.
     *
     * Falls back to auto-generated names from the post type slug when
     * $names is not defined on the model class.
     *
     * @param class-string<Model> $modelClass The model class.
     * @param string              $postType   The post type slug.
     * @return array{singular: string, plural: string, slug: string}
     * @since 1.0.0
     */
    private function buildNames(string $modelClass, string $postType): array
    {
        return [
            'singular' => $modelClass::$names['singular'] ?? Str::ucfirst($postType),
            'plural' => $modelClass::$names['plural'] ?? Str::ucfirst($postType) . 's',
            'slug' => $modelClass::$names['slug'] ?? Str::lower($postType),
        ];
    }

    /**
     * Translate $admin_columns to extended-cpts admin_cols format.
     *
     * Theme developers define $admin_columns as simple key => label arrays
     * and optionally implement get{Column}Column() methods for custom output.
     * This method converts that convention into the format expected by
     * johnbillion/extended-cpts.
     *
     * Three column types are supported:
     * 1. **Raw array** — passed through as-is (full extended-cpts syntax).
     * 2. **String label with method** — uses CurrentModelProxy to call the
     *    theme's get{Column}Column() method.
     * 3. **String label without method** — treated as a post meta key.
     *
     * @param class-string<Model> $modelClass The model class.
     * @return array<string, array<string, mixed>> The admin_cols array.
     * @since 1.0.0
     */
    private function buildAdminCols(string $modelClass): array
    {
        return collect($modelClass::$admin_columns)
            ->mapWithKeys(function ($label, $key) use ($modelClass) {
                if (is_array($label)) {
                    return [$key => $label];
                }

                $method = 'get' . ucfirst($key) . 'Column';

                return [
                    $key => method_exists($modelClass, $method)
                        ? ['title' => $label, 'callable' => [$modelClass, $method]]
                        : ['title' => $label, 'meta_key' => $key],
                ];
            })
            ->all();
    }
}
