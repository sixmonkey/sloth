<?php

declare(strict_types=1);

namespace Sloth\Finder;

/**
 * Interface for Finder implementations.
 *
 * @since 1.0.0
 */
interface IFinder
{
    /**
     * Find a file by name.
     *
     * @since 1.0.0
     *
     * @param string $name The file name or relative path
     *
     * @return string
     */
    public function find(string $name): string;

    /**
     * Register a list of paths.
     *
     * @since 1.0.0
     *
     * @param array<int|string, string> $paths Array of paths to register
     *
     * @return static
     */
    public function addPaths(array $paths): static;

    /**
     * Return a list of registered paths.
     *
     * @since 1.0.0
     *
     * @return array<int|string, string>
     */
    public function getPaths(): array;

    /**
     * Return a list of found files.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function getFiles(): array;
}
