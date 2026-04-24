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
 * that registers them via register_extended_post_type() on every request —
 * with zero discovery overhead after the first run.
 *
 * Layotter configuration is handled by LayotterServiceProvider which reads
 * sloth.models directly.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ModelManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Model::class);
    }

    protected function directory(): string
    {
        return 'Model';
    }

    protected function manifestName(): string
    {
        return 'models.manifest.php';
    }

    protected function extraLines(string $identifier, string $file): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $identifier;

        if (!$modelClass::$register) {
            return [];
        }

        $postType = $modelClass::getPostType();

        return [
            '\register_extended_post_type(' . var_export($postType,
                true) . ', ' . var_export($this->buildArgs($modelClass),
                true) . ', ' . var_export($this->buildNames($modelClass, $postType), true) . ');',
        ];
    }

    protected function bindings(array $map): array
    {
        return [
            'sloth.models' => collect($map)
                ->mapWithKeys(function ($file, $modelClass) {
                    /** @var class-string<Model> $modelClass */
                    return [$modelClass::getPostType() => $modelClass];
                })
                ->all(),
        ];
    }

    /**
     * @param class-string<Model> $modelClass
     * @return array<string, mixed>
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
     * @param class-string<Model> $modelClass
     * @return array{singular: string, plural: string}
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
     * Uses CurrentModelProxy so callables are [Class, 'method'] arrays — var_export safe.
     * Theme developers keep $admin_columns and get{Column}Column() unchanged.
     * If $label is already an array it is passed through as-is (raw extended-cpts syntax).
     *
     * @param class-string<Model> $modelClass
     * @return array<string, array<string, mixed>>
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
                        ? ['title' => $label, 'function' => [CurrentModelProxy::class, $method . 'Echo']]
                        : ['title' => $label, 'meta_key' => $key],
                ];
            })
            ->all();
    }
}
