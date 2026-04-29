<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\Application;

/**
 * Base class for manifest-based file loading.
 *
 * Orchestrates the discover → write → include lifecycle.
 * Subclasses define what to find and what extra lines to generate per entry.
 *
 * ## Lifecycle
 *
 * 1. init() — called on WordPress 'init' hook
 * 2. On cache miss: finder discovers files, ManifestWriter writes manifest
 * 3. require_once manifest — Opcache takes over from here
 *
 * ## Subclass responsibilities
 *
 * - finder()        — return the appropriate FinderInterface implementation
 * - directory()     — subdirectory name, scanned in both app/ and theme/
 * - manifestName()  — filename for the manifest in cache/
 * - extraLines()    — optional extra PHP lines per discovered identifier
 *
 * ## Container bindings
 *
 * After calling init(), use getDiscovered() to access the file map and
 * register container bindings in the service provider. Do not override
 * anything in the builder for this purpose.
 *
 * @since 1.0.0
 */
abstract class AbstractManifestBuilder
{
    /**
     * The discovered identifier from the last build.
     *
     * @var array<string, string>
     */
    protected array $discovered = [];

    public function __construct(protected Application $app) {}

    /**
     * Run discovery and load the manifest.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $manifest = app()->path('cache') . '/' . $this->manifestName();

        if (app()->isLocal() || !app('files')->exists($manifest)) {
            $this->build($manifest);
        }

        require_once $manifest;
    }

    /**
     * Run discovery and write the manifest.
     *
     * @since 1.0.0
     */
    protected function build(string $manifest): void
    {
        $map = $this->finder()->find($this->directories());

        $this->discovered = $map;

        $extraLines = collect($map)
            ->mapWithKeys(fn($file, $identifier) => [
                $identifier => $this->extraLines($identifier, $file),
            ])
            ->filter(fn($lines) => !empty($lines))
            ->all();

        (new ManifestWriter(app('files')))->write(
            path: $manifest,
            map: $map,
            extraLines: $extraLines,
        );
    }

    /**
     * Directories to scan — always app/{directory}/ and theme/{directory}/.
     *
     * Override if you need non-standard paths.
     *
     * @return list<string> Absolute paths.
     * @throws BindingResolutionException
     * @since 1.0.0
     */
    protected function directories(): array
    {
        return [
            app()->path($this->directory()),
            app()->path($this->directory(), 'theme'),
        ];
    }

    /**
     * Get the discovered file map from the last build.
     *
     * @return array<string, string>
     * @since 1.0.0
     */
    public function getDiscovered(): array
    {
        return $this->discovered;
    }

    /**
     * The finder to use for discovery.
     *
     * @since 1.0.0
     */
    abstract protected function finder(): FinderInterface;

    /**
     * Subdirectory name to scan, relative to app/ and theme/.
     * e.g. 'Model', 'Taxonomy', 'includes'
     *
     * @since 1.0.0
     */
    abstract protected function directory(): string;

    /**
     * Filename for the generated manifest in the cache directory.
     *
     * @since 1.0.0
     */
    abstract protected function manifestName(): string;

    /**
     * Extra PHP lines to write into the manifest after require_once for this identifier.
     *
     * @param string $identifier Class name or file path, depending on the finder.
     * @param string $file       Absolute path to the file.
     * @return list<string>
     * @since 1.0.0
     */
    protected function extraLines(string $identifier, string $file): array
    {
        return [];
    }
}
