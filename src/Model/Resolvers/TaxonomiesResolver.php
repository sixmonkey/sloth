<?php

namespace Sloth\Model\Resolvers;

use Sloth\Model\Taxonomy;
use Sloth\Utility\ClassResolver\ClassResolver;

/**
 * Resolver for discovering taxonomy classes.
 *
 * Automatically discovers and caches all classes in the Taxonomy/ directory
 * that extend \Sloth\Model\Taxonomy.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 * @see \Sloth\Utility\ClassResolver\ClassResolver
 */
class TaxonomiesResolver extends ClassResolver
{
    /**
     * The directory where taxonomy classes are located.
     *
     * @var string
     */
    protected static string $dir = 'Taxonomy';

    /**
     * The cache key for storing resolved classes.
     *
     * @var string
     */
    protected static string $cacheKey = 'sloth.class-resolver.taxonomies';

    /**
     * The base class that all taxonomy classes should extend.
     *
     * @var string
     */
    protected static string $subclassOf = Taxonomy::class;
}
