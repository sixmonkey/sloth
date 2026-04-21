<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Sloth\Model\Concerns\PostScopes;
use Sloth\Model\Builder\PostBuilder;
use Mockery;

/**
 * Unit tests for the PostScopes trait.
 *
 * These tests verify that the trait correctly adds WordPress-specific
 * query scopes for filtering and finding posts.
 */
describe('PostScopes', function (): void {
    /**
     * A minimal model class that uses the trait for testing.
     */
    class TestModelWithPostScopes
    {
        use PostScopes;
    }

    describe('scopeStatus()', function (): void {
        it('filters by post status', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_status', 'draft')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeStatus($query, 'draft');
            expect($result)->toBe($query);
        });
    });

    describe('scopeSlug()', function (): void {
        it('filters by post slug', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_name', 'my-post-slug')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeSlug($query, 'my-post-slug');
            expect($result)->toBe($query);
        });
    });

    describe('scopeType()', function (): void {
        it('filters by post type', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_type', 'page')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeType($query, 'page');
            expect($result)->toBe($query);
        });
    });

    describe('scopeTypeIn()', function (): void {
        it('filters by multiple post types', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('whereIn')
                ->with('post_type', ['post', 'page'])
                ->once()
                ->andReturnSelf();

            $result = $model->scopeTypeIn($query, ['post', 'page']);
            expect($result)->toBe($query);
        });
    });

    describe('scopeParent()', function (): void {
        it('filters by parent post ID', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_parent', 42)
                ->once()
                ->andReturnSelf();

            $result = $model->scopeParent($query, 42);
            expect($result)->toBe($query);
        });

        it('accepts string parent ID', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_parent', '42')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeParent($query, '42');
            expect($result)->toBe($query);
        });
    });

    describe('scopeSearch()', function (): void {
        it('searches across title, excerpt, and content', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $innerQuery = Mockery::mock(Builder::class);

            $query->shouldReceive('where')
                ->withArgs(function ($callback) {
                    return is_callable($callback);
                })
                ->once()
                ->andReturnUsing(function ($callback) use ($query, $innerQuery): Builder {
                    $callback($innerQuery);
                    return $query;
                });

            $innerQuery->shouldReceive('where')
                ->with('post_title', 'like', '%wordpress%')
                ->once()
                ->andReturnSelf();

            $innerQuery->shouldReceive('orWhere')
                ->with('post_excerpt', 'like', '%wordpress%')
                ->once()
                ->andReturnSelf();

            $innerQuery->shouldReceive('orWhere')
                ->with('post_content', 'like', '%wordpress%')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeSearch($query, 'wordpress');
            expect($result)->toBe($query);
        });
    });

    describe('scopePublished()', function (): void {
        it('filters to published posts with date constraint', function (): void {
            $query = Mockery::mock(Builder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_status', 'publish')
                ->once()
                ->andReturnSelf();

            $query->shouldReceive('where')
                ->withAnyArgs()
                ->once()
                ->andReturnSelf();

            $result = $model->scopePublished($query);
            expect($result)->toBe($query);
        });
    });

    describe('scopeFindBySlugOrId()', function (): void {
        it('finds by slug or ID', function (): void {
            $query = Mockery::mock(PostBuilder::class);
            $model = new TestModelWithPostScopes();

            $query->shouldReceive('where')
                ->with('post_name', 'my-slug')
                ->once()
                ->andReturnSelf();

            $query->shouldReceive('orWhere')
                ->with('ID', 'my-slug')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeFindBySlugOrId($query, 'my-slug');
            expect($result)->toBe($query);
        });
    });
});
