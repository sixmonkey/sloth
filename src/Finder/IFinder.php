<?php

namespace Sloth\Finder;

interface IFinder
{
    /**
     * Register a list of paths.
     *
     * @param array $paths
     *
     * @return mixed
     */
    public function addPaths(array $paths);
    /**
     * Returns a file path.
     *
     * @param string $name
     *
     * @return string
     */
    public function find($name);

    /**
     * Return a list of found files.
     *
     * @return array
     */
    public function getFiles();

    /**
     * Return a list of registered paths.
     *
     * @return array
     */
    public function getPaths();
}
