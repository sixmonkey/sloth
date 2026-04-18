<?php

namespace Sloth\Utility\ClassResolver;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Sloth\Model\Model;
use Symfony\Component\Finder\Finder;

/**
 * Base class for resolving/discovering classes in a directory.
 *
 * Provides automatic discovery and caching of classes that extend a given
 * base class within a specified directory. Used by Sloth to find:
 * - Models (Post types)
 * - Taxonomies
 * - API Controllers
 * - Modules
 *
 * @since 1.0.0
 */
abstract class ClassResolver
{
    /**
     * The directory where classes are located.
     *
     * Override this in subclasses to specify the directory to scan.
     *
     * @var string
     */
    protected static string $dir = 'Models/PostType';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.classes';

    /**
     * The base class that all resolved classes should extend.
     *
     * @var string
     */
    protected static string $subclassOf = Model::class;

    /**
     * Resolve all classes in the directory.
     *
     * @return Collection<string> Collection of class names
     * @throws ReflectionException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function resolve(): Collection
    {
        return self::collectClasses();
    }

    /**
     * Collect all classes from the configured directory.
     *
     * Scans the configured directory for PHP files, includes them,
     * and returns only classes that extend the configured base class.
     *
     * @return Collection<string> Collection of matching class names
     * @throws ReflectionException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private static function collectClasses(): Collection
    {
        $paths = collect([
            app()->path(static::$dir),
            app()->path(static::$dir, 'theme'),
        ])
            ->filter(fn($path) => is_dir($path));

        #$namespace = app()->getNamespace();

        $classes = [];

        foreach (new Finder()->in($paths->toArray())->files() as $class) {
            $file = realpath($class->getPathname());

            include_once $file;

            $class = collect(get_declared_classes())
                ->filter(function ($c) use ($file) {
                    $reflection = new ReflectionClass($c);
                    return $reflection->getFilename() === $file;
                })
            ->first();
            if (
                $class
                && is_subclass_of($class, static::$subclassOf)
                && !new ReflectionClass($class)->isAbstract()
            ) {
                $classes[] = $class;
            }
        }
        return collect($classes);
    }
}
