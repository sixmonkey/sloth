<?php

namespace Sloth\Model\Resolvers;

use Sloth\Model\Model;
use Sloth\Utility\ClassResolver\ClassResolver;

class ModelsResolver extends ClassResolver
{
    /**
     * @var string The directory where post type classes are located.
     */
    protected static string $dir = 'Model';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.models';


    /**
     * @var string The base class that all post type classes should extend.
     */
    protected static string $subclassOf = Model::class;

}
