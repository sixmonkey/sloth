<?php

namespace Sloth\Module\Resolvers;

use Sloth\Module\Module;
use Sloth\Utility\ClassResolver\ClassResolver;

/**
 * Resolver for discovering module classes.
 *
 * Automatically discovers and caches all classes in the Module/ directory
 * that extend \Sloth\Module\Module.
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module
 * @see \Sloth\Utility\ClassResolver\ClassResolver
 */
class ModulesResolver extends ClassResolver
{
    /**
     * The directory where module classes are located.
     *
     * @var string
     */
    protected static string $dir = 'Module';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.modules';

    /**
     * The base class that all module classes should extend.
     *
     * @var string
     */
    protected static string $subclassOf = Module::class;
}
