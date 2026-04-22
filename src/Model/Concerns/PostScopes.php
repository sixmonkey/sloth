<?php

declare(strict_types=1);

namespace Sloth\Model\Concerns;

use Carbon\Carbon;
use Corcel\Model\Meta\PostMeta;
use Illuminate\Database\Eloquent\Builder;
use Sloth\Model\Builder\PostBuilder;

/**
 * Provides WordPress-specific query scopes for post models.
 *
 * This trait extracts common WordPress query patterns into reusable scopes.
 * It replaces relying on Corcel's built-in scopes with explicit, well-documented
 * implementations that have full control over their behavior.
 *
 * ## Usage
 *
 * ```php
 * class Post extends Model
 * {
 *     use PostScopes;
 * }
 *
 * // Filter by post status
 * $drafts = Post::status('draft')->get();
 *
 * // Get published posts only
 * $published = Post::published()->get();
 *
 * // Filter by post type
 * $pages = Post::type('page')->get();
 *
 * // Find by slug or ID
 * $post = Post::findBySlugOrId('my-post-slug');
 *
 * // Filter by taxonomy terms
 * $categorized = Post::taxonomy('category', 'news')->get();
 *
 * // Search across title, excerpt, and content
 * $results = Post::search('keyword')->get();
 *
 * // Get the homepage post
 * $homepage = Post::home()->first();
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Model\Builder\PostBuilder
 */
trait PostScopes
{
    /**
     * Filter by post status.
     *
     * Common status values: 'publish', 'draft', 'pending', 'private', 'trash'
     *
     * @param Builder $query The query builder
     * @param string $status The post status to filter by
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $drafts = Post::status('draft')->get();
     * ```
     */
    public function scopeStatus(Builder $query, string $status): Builder
    {
        return $query->where('post_status', $status);
    }

    /**
     * Filter to only published posts.
     *
     * Uses Carbon for consistent date handling. Only returns posts
     * with post_status = 'publish' that have a published date.
     *
     * @param Builder $query The query builder
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $publishedPosts = Post::published()->get();
     * ```
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('post_status', 'publish')
            ->where('post_date', '<=', Carbon::now());
    }

    /**
     * Filter by post type.
     *
     * @param Builder $query The query builder
     * @param string $type The post type slug (e.g., 'post', 'page', 'project')
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $pages = Post::type('page')->get();
     * ```
     */
    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('post_type', $type);
    }

    /**
     * Filter by multiple post types.
     *
     * @param Builder $query The query builder
     * @param array $types Array of post type slugs
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $postsAndPages = Post::typeIn(['post', 'page'])->get();
     * ```
     */
    public function scopeTypeIn(Builder $query, array $types): Builder
    {
        return $query->whereIn('post_type', $types);
    }

    /**
     * Filter by post slug (post_name).
     *
     * @param Builder $query The query builder
     * @param string $slug The URL slug to search for
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $post = Post::slug('hello-world')->first();
     * ```
     */
    public function scopeSlug(Builder $query, string $slug): Builder
    {
        return $query->where('post_name', $slug);
    }

    /**
     * Filter by parent post ID.
     *
     * Useful for hierarchical post types like pages or hierarchical CPTs.
     *
     * @param Builder $query The query builder
     * @param int|string $parentId The parent post ID (use 0 for root-level posts)
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * // Get child pages
     * $children = Page::parent(get_the_ID())->get();
     *
     * // Get root-level pages only
     * $rootPages = Page::parent(0)->get();
     * ```
     */
    public function scopeParent(Builder $query, int|string $parentId): Builder
    {
        return $query->where('post_parent', $parentId);
    }

    /**
     * Filter posts by taxonomy terms.
     *
     * Uses a whereHas clause to match posts that have terms
     * in the specified taxonomy.
     *
     * @param Builder $query The query builder
     * @param string $taxonomy The taxonomy name (e.g., 'category', 'post_tag')
     * @param string|array $terms Term slug(s) to match
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * // Posts in 'news' category
     * $news = Post::taxonomy('category', 'news')->get();
     *
     * // Posts with any of these tags
     * $tagged = Post::taxonomy('post_tag', ['php', 'wordpress'])->get();
     * ```
     */
    public function scopeTaxonomy(Builder $query, string $taxonomy, string|array $terms): Builder
    {
        $terms = (array) $terms;

        return $query->whereHas('taxonomies', function (Builder $q) use ($taxonomy, $terms): Builder {
            return $q->where('taxonomy', $taxonomy)
                ->whereHas('term', function (Builder $q) use ($terms): Builder {
                    return $q->whereIn('slug', $terms);
                });
        });
    }

    /**
     * Search across post title, excerpt, and content.
     *
     * Performs a LIKE search on post_title, post_excerpt, and post_content.
     *
     * @param Builder $query The query builder
     * @param string $term The search term
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $results = Post::search('wordpress theme')->get();
     * ```
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = '%' . $term . '%';

        return $query->where(function (Builder $q) use ($term): Builder {
            return $q->where('post_title', 'like', $term)
                ->orWhere('post_excerpt', 'like', $term)
                ->orWhere('post_content', 'like', $term);
        });
    }

    /**
     * Get the homepage post (page_on_front).
     *
     * Returns the post configured as the static front page.
     *
     * @param Builder $query The query builder
     * @return Builder The filtered query
     *
     * @example
     * ```php
     * $homepage = Post::home()->first();
     * ```
     */
    public function scopeHome(Builder $query): Builder
    {
        return $query
            ->where('ID', '=', (int) get_option('page_on_front'))
            ->limit(1);
    }

    /**
     * Order query by a meta field.
     *
     * Uses FIELD() MySQL function for explicit ordering based on
     * meta field values. Note: This is inefficient for large datasets
     * as it requires fetching all meta values first.
     *
     * @param PostBuilder $query The query builder
     * @param string $meta The meta key to order by
     * @param string $direction Sort direction ('asc' or 'desc')
     * @return void
     *
     * @example
     * ```php
     * $posts = Post::orderByMeta('priority', 'asc')->get();
     * ```
     */
    public function scopeOrderByMeta(PostBuilder $query, string $meta, string $direction = 'asc'): void
    {
        $postIds = PostMeta::where('meta_key', $meta)
            ->orderBy('meta_value', $direction)
            ->pluck('post_id')
            ->unique()
            ->take(1000)
            ->toArray();

        if (empty($postIds)) {
            return;
        }

        $query->orderByRaw('FIELD(ID, ' . implode(',', $postIds) . ')');
    }

    /**
     * Find a post by its slug or ID.
     *
     * Useful for permalink handling where the value could be either.
     *
     * @param PostBuilder $query The query builder
     * @param string $slugOrId The slug or ID to search for
     * @return PostBuilder The filtered query
     *
     * @example
     * ```php
     * $post = Post::findBySlugOrId('my-post')->first();
     * $post = Post::findBySlugOrId(123)->first();
     * ```
     */
    public function scopeFindBySlugOrId(PostBuilder $query, string $slugOrId): PostBuilder
    {
        return $query->where('post_name', $slugOrId)->orWhere('ID', $slugOrId);
    }
}
