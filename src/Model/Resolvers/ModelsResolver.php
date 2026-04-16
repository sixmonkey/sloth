<?php

namespace Sloth\Model\Resolvers;

use Sloth\Model\Model;
use Sloth\Utility\ClassResolver\ClassResolver;

/**
 * Resolver for discovering model/post type classes.
 *
 * Automatically discovers and caches all classes in the Model/ directory
 * that extend \Sloth\Model\Model.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Utility\ClassResolver\ClassResolver
 */
class ModelsResolver extends ClassResolver
{
    /**
     * The directory where model classes are located.
     *
     * @var string
     */
    protected static string $dir = 'Model';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.models';

    /**
     * The base class that all model classes should extend.
     *
     * @var string
     */
    protected static string $subclassOf = Model::class;
}
