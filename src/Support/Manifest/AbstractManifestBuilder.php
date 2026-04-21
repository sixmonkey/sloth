<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

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
 * - bindings()      — what to store in the container at the end of the manifest
 *
 * @since 1.0.0
 */
abstract class AbstractManifestBuilder
{
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

        $extraLines = collect($map)
            ->mapWithKeys(fn($file, $identifier) => [
                $identifier => $this->extraLines($identifier, $file),
            ])
            ->filter(fn($lines) => !empty($lines))
            ->all();

        (new ManifestWriter())->write(
            path: $manifest,
            map: $map,
            extraLines: $extraLines,
            containerBindings: $this->bindings($map),
        );
    }

    /**
     * Directories to scan — always app/{directory}/ and theme/{directory}/.
     *
     * Override if you need non-standard paths.
     *
     * @return list<string> Absolute paths.
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

    /**
     * Container bindings to write at the end of the manifest.
     *
     * @param array<string, string> $map Identifier => file map from the finder.
     * @return array<string, mixed>
     * @since 1.0.0
     */
    protected function bindings(array $map): array
    {
        return [];
    }
}
