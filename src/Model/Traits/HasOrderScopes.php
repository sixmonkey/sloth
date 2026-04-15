<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Provides ordering query scopes for models.
 *
 * This trait adds common query scopes for ordering results by creation date.
 * It replaces Corcel's OrderScopes trait to maintain control over the
 * model's behavior without depending on Corcel's internal traits.
 *
 * @example
 * ```php
 * // Get newest posts first
 * $posts = Post::newest()->get();
 *
 * // Get oldest comments first
 * $comments = Comment::oldest()->get();
 * ```
 *
 * @see \Corcel\Concerns\OrderScopes Original source
 */
trait HasOrderScopes
{
    /**
     * Order results by creation date, newest first.
     *
     * Applies an ORDER BY clause on the model's CREATED_AT constant
     * in descending order (most recent first).
     *
     * @param Builder $query The query builder instance
     *
     * @return Builder The modified query builder
     *
     * @example
     * ```php
     * // Get the 10 most recent posts
     * $recentPosts = Post::newest()->limit(10)->get();
     * ```
     */
    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderBy(static::CREATED_AT, 'desc');
    }

    /**
     * Order results by creation date, oldest first.
     *
     * Applies an ORDER BY clause on the model's CREATED_AT constant
     * in ascending order (oldest first).
     *
     * @param Builder $query The query builder instance
     *
     * @return Builder The modified query builder
     *
     * @example
     * ```php
     * // Get posts from the beginning
     * $oldestPosts = Post::oldest()->limit(10)->get();
     * ```
     */
    public function scopeOldest(Builder $query): Builder
    {
        return $query->orderBy(static::CREATED_AT, 'asc');
    }
}
