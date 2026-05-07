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
    protected $paths = [];

    /**
     * List of found files.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected $files = [];

    /**
     * Allowed file extensions.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected $extensions = [];

    /**
     * Register a path.
     *
     * @param int|string $key The file URL if defined or numeric index
     * @param string $path The path to register
     *
     * @return $this
     * @since 1.0.0
     *
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
     * @param array<int|string, string> $paths Array of paths to register
     *
     * @return $this
     * @since 1.0.0
     *
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
     * @return array<int|string, string>
     * @since 1.0.0
     *
     */
    #[\Override]
    public function getPaths(): array
    {
        return $this->paths;
    }

    /**
     * Return a list of found files.
     *
     * @return array<string, string>
     * @since 1.0.0
     *
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * Find a file by name.
     *
     * @param string $name The file name or relative path
     *
     * @throws FinderException
     * @since 1.0.0
     *
     */
    #[\Override]
    public function find(mixed $name): string
    {
        $name = (string)$name;
        return $this->files[$name] ?? ($this->files[$name] = $this->findInPaths($name, $this->paths));
    }

    /**
     * Look after a file in registered paths.
     *
     * @param string $name The file name or relative path
     * @param array<int, string> $paths Registered paths to search
     *
     * @throws FinderException
     * @since 1.0.0
     *
     */
    #[\Override]
    protected function findInPaths(mixed $name, mixed $paths): string
    {
        $name = (string)$name;
        $paths = (array)$paths;
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
     * @param string $name The file name or relative path
     *
     * @return array<int, string>
     * @since 1.0.0
     *
     */
    protected function getPossibleFiles(string $name): array
    {
        return array_map(
            fn(string $extension): string => str_replace('.', '/', $name) . '.' . $extension,
            $this->extensions
        );
    }
}
