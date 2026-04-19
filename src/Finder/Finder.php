<?php

declare(strict_types=1);

namespace Sloth\Finder;

use Illuminate\View\FileViewFinder as IlluminateFileViewFinder;

/**
 * Abstract Finder class for locating files.
 *
 * @since 1.0.0
 */
abstract class Finder extends IlluminateFileViewFinder
{
    /**
     * List of given/registered paths.
     *
     * @since 1.0.0
     * @var array<int|string, string>
     */
    protected array $paths = [];

    /**
     * List of found files.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $files = [];

    /**
     * Allowed file extensions.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected array $extensions = [];

    /**
     * Register a path.
     *
     * @since 1.0.0
     *
     * @param int|string $key  The file URL if defined or numeric index
     * @param string     $path The path to register
     *
     * @return $this
     */
    protected function addPath(int|string $key, string $path): static
    {
        if (!in_array($path, $this->paths, true)) {
            if (is_numeric($key)) {
                $this->paths[] = $path;
            } else {
                $this->paths[$key] = $path;
            }
        }

        return $this;
    }

    /**
     * Register multiple file paths.
     *
     * @since 1.0.0
     *
     * @param array<int|string, string> $paths Array of paths to register
     *
     * @return $this
     */
    public function addPaths(array $paths): static
    {
        foreach ($paths as $index => $path) {
            $this->addPath($index, $path);
        }

        return $this;
    }

    /**
     * Return a list of registered paths.
     *
     * @since 1.0.0
     *
     * @return array<int|string, string>
     */
    #[\Override]
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Return a list of found files.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Find a file by name.
     *
     * @since 1.0.0
     *
     * @param string $name The file name or relative path
     *
     * @throws FinderException
     */
    #[\Override]
    public function find(string $name): string
    {
        return $this->files[$name] ?? ($this->files[$name] = $this->findInPaths($name, $this->paths));
    }

    /**
     * Look after a file in registered paths.
     *
     * @since 1.0.0
     *
     * @param string               $name  The file name or relative path
     * @param array<int, string>   $paths Registered paths to search
     *
     * @throws FinderException
     */
    #[\Override]
    protected function findInPaths(string $name, array $paths): string
    {
        foreach ($paths as $path) {
            foreach ($this->getPossibleFiles($name) as $file) {
                $filePath = $path . $file;
                if (app('files')->exists($filePath)) {
                    return $filePath;
                }
            }
        }

        throw new FinderException('File or entity "' . $name . '" not found.');
    }

    /**
     * Returns a list of possible file names.
     *
     * @since 1.0.0
     *
     * @param string $name The file name or relative path
     *
     * @return array<int, string>
     */
    protected function getPossibleFiles(string $name): array
    {
        return array_map(
            fn(string $extension): string => str_replace('.', '/', $name) . '.' . $extension,
            $this->extensions
        );
    }
}
