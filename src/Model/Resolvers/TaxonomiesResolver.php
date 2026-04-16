<?php

namespace Sloth\Model\Resolvers;

use Sloth\Model\Taxonomy;
use Sloth\Utility\ClassResolver\ClassResolver;

class TaxonomiesResolver extends ClassResolver
{
    /**
     * @var string The directory where taxonomies classes are located.
     */
    protected static string $dir = 'Taxonomy';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.taxonomies';


    /**
     * @var string The base class that all txonomy classes should extend.
     */
    protected static string $subclassOf = Taxonomy::class;

}
