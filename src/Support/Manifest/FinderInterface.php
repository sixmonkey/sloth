<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

/**
 * Discovers files to be included in a manifest.
 *
 * Returns a map of [identifier => absolute_file_path] where identifier
 * is either a fully-qualified class name (ClassMapFinder) or the file
 * path itself (FileFinder).
 *
 * @since 1.0.0
 */
interface FinderInterface
{
    /**
     * Scan the given directories and return discovered files.
     *
     * @param  list<string>            $paths Absolute directory paths to scan.
     * @return array<string, string>   Map of identifier => absolute file path.
     */
    public function find(array $paths): array;
}
