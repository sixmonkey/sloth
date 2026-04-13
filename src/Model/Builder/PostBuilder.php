<?php

declare(strict_types=1);

namespace Sloth\Model\Builder;

use Corcel\Model\Builder\PostBuilder as CorcelPostBuilder;

/**
 * Custom query builder for WordPress posts.
 *
 * Extends Corcel's PostBuilder to add Sloth-specific query logic,
 * particularly for handling post type filters correctly with revisions.
 *
 * @since 1.0.0
 * @see CorcelPostBuilder For base functionality
 */
class PostBuilder extends CorcelPostBuilder
{
    /**
     * Add a post type constraint to the query.
     *
     * Skips the filter if currently querying revisions to avoid
     * conflicting with revision-specific queries.
     *
     * @since 1.0.0
     *
     * @param mixed $type Post type name or array of types
     * @return $this
     */
    #[\Override]
    public function type($type)
    {
        if ($this->isQueryingRevisions()) {
            return $this;
        }

        return parent::type($type);
    }

    /**
     * Check if the current query is for revisions.
     *
     * Inspects the query's where conditions to detect if querying
     * for post_type = 'revision'.
     *
     * @since 1.0.0
     *
     * @return bool True if querying revisions
     */
    protected function isQueryingRevisions(): bool
    {
        $wheres = $this->query->wheres ?? [];

        foreach ($wheres as $where) {
            if (isset($where['column'], $where['value'])
                && $where['value'] === 'revision'
                && ($where['column'] === 'post_type' || $where['column'] === 'posts.post_type')) {
                return true;
            }
        }

        return false;
    }
}
