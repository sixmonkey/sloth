<?php

namespace Sloth\Utility\ClassResolver;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;
use Sloth\Model\Model;
use Symfony\Component\Finder\Finder;

abstract class ClassResolver
{
    /**
     * @var string The directory where post type classes are located.
     */
    protected static string $dir = 'Models/PostType';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.classes';


    /**
     * @var string The base class that all post type classes should extend.
     */
    protected static string $subclassOf = Model::class;

    /**
     * @throws ReflectionException|\Illuminate\Contracts\Container\BindingResolutionException
     */
    public static function resolve(): Collection
    {
        return self::collectClasses();
    }

    /**
     * @return Collection
     * @throws ReflectionException|\Illuminate\Contracts\Container\BindingResolutionException
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

        foreach ((new Finder)->in($paths->toArray())->files() as $class) {
            $file = realpath($class->getPathname());

            include_once $file;

            $class = collect(get_declared_classes())
                ->filter(function($c) use ($file) {
                    $reflection = new ReflectionClass($c);
                    return $reflection->getFilename() === $file;
            })
            ->first();
            if (
                $class &&
                is_subclass_of($class, static::$subclassOf) &&
                !new ReflectionClass($class)->isAbstract()
            ) {
                $classes[] = $class;
            }
        }
        return collect($classes);
    }
}
