<?php

declare(strict_types=1);

namespace Sloth\Api\Resolvers;

use Sloth\Api\Controller;
use Sloth\Utility\ClassResolver\ClassResolver;

/**
 * Resolver for discovering API controller classes.
 *
 * Automatically discovers and caches all classes in the Api/ directory
 * that extend \Sloth\Api\Controller.
 *
 * @since 1.0.0
 * @see \Sloth\Api\Controller
 * @see \Sloth\Utility\ClassResolver\ClassResolver
 */
class ApiControllersResolver extends ClassResolver
{
    /**
     * The directory where API controller classes are located.
     *
     * @var string
     * @since 1.0.0
     */
    protected static string $dir = 'Api';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     * @since 1.0.0
     */
    protected static string $cacheKey = 'sloth.class-resolver.api-controllers';

    /**
     * The base class that all API controller classes should extend.
     *
     * @var string
     * @since 1.0.0
     */
    protected static string $subclassOf = Controller::class;
}
