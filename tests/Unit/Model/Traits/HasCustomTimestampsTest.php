<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Traits;

use Sloth\Model\Traits\HasCustomTimestamps;

/**
 * Unit tests for the HasCustomTimestamps trait.
 *
 * These tests verify that the trait correctly sets both local
 * and GMT timestamp fields when creating or updating models.
 */
describe('HasCustomTimestamps', function (): void {
    /**
     * A minimal Eloquent model class that uses the trait for testing.
     */
    class TestModelWithCustomTimestamps extends \Illuminate\Database\Eloquent\Model
    {
        use HasCustomTimestamps;

        protected $table = 'test_table';
        public const CREATED_AT = 'post_date';
        public const UPDATED_AT = 'post_modified';

        public ?string $post_date = null;
        public ?string $post_date_gmt = null;
        public ?string $post_modified = null;
        public ?string $post_modified_gmt = null;
    }

    describe('setCreatedAt()', function (): void {
        it('sets both local and GMT timestamp fields', function (): void {
            $model = new TestModelWithCustomTimestamps();
            $model->setCreatedAt('2024-01-15 10:30:00');

            expect($model->post_date)->toBe('2024-01-15 10:30:00');
            expect($model->post_date_gmt)->toBe('2024-01-15 10:30:00');
        });

        it('handles different timestamp formats', function (): void {
            $model = new TestModelWithCustomTimestamps();
            $model->setCreatedAt('2024-12-25 23:59:59');

            expect($model->post_date)->toBe('2024-12-25 23:59:59');
            expect($model->post_date_gmt)->toBe('2024-12-25 23:59:59');
        });
    });

    describe('setUpdatedAt()', function (): void {
        it('sets both local and GMT timestamp fields', function (): void {
            $model = new TestModelWithCustomTimestamps();
            $model->setUpdatedAt('2024-01-15 14:45:00');

            expect($model->post_modified)->toBe('2024-01-15 14:45:00');
            expect($model->post_modified_gmt)->toBe('2024-01-15 14:45:00');
        });

        it('handles different timestamp formats', function (): void {
            $model = new TestModelWithCustomTimestamps();
            $model->setUpdatedAt('2024-06-30 08:00:00');

            expect($model->post_modified)->toBe('2024-06-30 08:00:00');
            expect($model->post_modified_gmt)->toBe('2024-06-30 08:00:00');
        });
    });
});
