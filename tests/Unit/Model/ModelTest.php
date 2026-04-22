<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit;

use Sloth\Model\Model;
use Sloth\Model\Post;

/**
 * Tests for core Model classes.
 */
describe('Model Classes', function (): void {
    describe('Model', function (): void {
        it('has correct table', function (): void {
            $model = new Model();
            expect($model->getTable())->toBe('posts');
        });

        it('has correct primary key', function (): void {
            $model = new Model();
            expect($model->getKeyName())->toBe('ID');
        });
    });

    describe('Post', function (): void {
        it('extends Model', function (): void {
            $post = new Post();
            expect($post)->toBeInstanceOf(Model::class);
        });

        it('has correct table', function (): void {
            $post = new Post();
            expect($post->getTable())->toBe('posts');
        });

        it('has correct primary key', function (): void {
            $post = new Post();
            expect($post->getKeyName())->toBe('ID');
        });
    });
});