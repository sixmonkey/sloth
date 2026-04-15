<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Traits;

use Sloth\Model\Traits\HasOrderScopes;
use Mockery;

/**
 * Unit tests for the HasOrderScopes trait.
 *
 * These tests verify that the trait correctly adds query scopes
 * for ordering results by creation date.
 */
describe('HasOrderScopes', function (): void {
    /**
     * A minimal model class that uses the trait for testing.
     */
    class TestModelWithOrderScopes
    {
        use HasOrderScopes;

        public const CREATED_AT = 'created_at';
    }

    describe('scopeNewest()', function (): void {
        it('orders by CREATED_AT in descending order', function (): void {
            $query = \Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
            $model = new TestModelWithOrderScopes();

            $query->shouldReceive('orderBy')
                ->with('created_at', 'desc')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeNewest($query);
            expect($result)->toBe($query);
        });
    });

    describe('scopeOldest()', function (): void {
        it('orders by CREATED_AT in ascending order', function (): void {
            $query = \Mockery::mock(\Illuminate\Database\Eloquent\Builder::class);
            $model = new TestModelWithOrderScopes();

            $query->shouldReceive('orderBy')
                ->with('created_at', 'asc')
                ->once()
                ->andReturnSelf();

            $result = $model->scopeOldest($query);
            expect($result)->toBe($query);
        });
    });
});
