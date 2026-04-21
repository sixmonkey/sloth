<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Composer\ClassMapGenerator\ClassMapGenerator;

/**
 * Discovers PHP classes via tokenization.
 *
 * Uses composer/class-map-generator to scan directories without loading files.
 * Optionally filters to only classes extending a given base class — resolved
 * via ReflectionClass::isSubclassOf() which is transitive and requires no
 * instantiation, making it safe for classes with any constructor signature.
 *
 * Returns [FullyQualifiedClassName => absolute_file_path].
 *
 * @since 1.0.0
 */
class ClassMapFinder implements FinderInterface
{
    /**
     * @param class-string|null $subclassOf Only return classes extending this. Null = all classes.
     */
    public function __construct(
        private readonly ?string $subclassOf = null,
    ) {}

    public function find(array $paths): array
    {
        $generator = new ClassMapGenerator();

        collect($paths)
            ->filter(fn($path) => is_dir($path))
            ->each(fn($path) => $generator->scanPaths($path));

        $classMap = $generator->getClassMap();
        $classMap->sort();

        return collect($classMap->getMap())
            ->filter(function (string $file, string $class): bool {
                require_once $file;

                $reflection = new \ReflectionClass($class);

                if ($reflection->isAbstract()) {
                    return false;
                }

                if ($this->subclassOf !== null && !$reflection->isSubclassOf($this->subclassOf)) {
                    return false;
                }

                return true;
            })
            ->all();
    }
}
