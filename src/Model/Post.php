<?php

declare(strict_types=1);

namespace Sloth\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Post Model
 *
 * Extends Sloth's base Model class. This model inherits all functionality
 * from Sloth\Model\Model, including ACF integration, taxonomy relationships,
 * and WordPress-specific query scopes.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model For the base implementation
 *
 * @property string $content The post content with WordPress filters applied
 *
 * @example
 * ```php
 * // Get a post by ID
 * $post = Post::find(123);
 *
 * // Get the filtered content
 * echo $post->content;
 *
 * // Get posts by category
 * $posts = Post::where('category', 'news')->get();
 * ```
 */
class Post extends Model
{
    /**
     * Gets the post content with WordPress filters applied.
     *
     * This accessor ensures that the content is processed through
     * WordPress's content filters, which handles things like
     * shortcodes, embeds, and paragraph formatting.
     *
     * @since 1.0.0
     *
     * @return string The filtered post content
     *
     * @uses apply_filters() To apply the_content filter
     */
    #[\Override]
    public function getContentAttribute(): string
    {
        return (string) apply_filters('the_content', $this->post_content ?? '');
    }

    /**
     * Get post revisions.
     *
     * @since 1.0.0
     *
     * @return HasMany The revisions relationship
     */
    #[\Override]
    public function revision(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'revision');
    }
}
