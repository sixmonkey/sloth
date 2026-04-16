<?php

namespace Sloth\Api\Resolvers;

use Sloth\Api\Controller;
use Sloth\Utility\ClassResolver\ClassResolver;

class ApiControllersResolver extends ClassResolver
{
    /**
     * @var string The directory where post type classes are located.
     */
    protected static string $dir = 'Api';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.api-controllers';


    /**
     * @var string The base class that all post type classes should extend.
     */
    protected static string $subclassOf = Controller::class;

}
