<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Post as CorcelPost;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Sloth\Model\Builder\PostBuilder;

/**
 * Post Model
 *
 * Extends Corcel's Post model to add Sloth-specific functionality,
 * primarily the processed content attribute.
 *
 * @since 1.0.0
 * @see CorcelPost For the base Corcel Post implementation
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
class Post extends CorcelPost
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
    public function getContentAttribute(): string
    {
        return (string) apply_filters('the_content', $this->post_content ?? '');
    }

    /**
     * @return PostBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new PostBuilder($query);
    }

    public function revision(): HasMany
    {
        return $this->hasMany(static::class, 'post_parent')
            ->where('post_type', 'revision');
    }
}
