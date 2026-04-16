<?php

namespace Sloth\Module\Resolvers;

use Sloth\Module\Module;
use Sloth\Utility\ClassResolver\ClassResolver;

class ModulesResolver extends ClassResolver
{
    /**
     * @var string The directory where module classes are located.
     */
    protected static string $dir = 'Module';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.modules';


    /**
     * @var string The base class that all module classes should extend.
     */
    protected static string $subclassOf = Module::class;

}
