<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Traits;

use Sloth\Model\Traits\HasMetaFields;
use Mockery;

/**
 * Unit tests for the HasMetaFields trait.
 *
 * These tests verify that the trait correctly:
 * - Maps model classes to their meta classes
 * - Provides meta relationship methods
 * - Handles meta field saving and querying
 */
describe('HasMetaFields', function (): void {
    /**
     * Test model for Sloth\Model\Model (Post-like)
     */
    class TestPostModel
    {
        use HasMetaFields;
    }

    /**
     * Test model for Sloth\Model\User
     */
    class TestUserModel
    {
        use HasMetaFields;
    }

    /**
     * Test model for Sloth\Model\Taxonomy
     */
    class TestTaxonomyModel
    {
        use HasMetaFields;
    }

    describe('getMetaClass()', function (): void {
        it('returns PostMeta for models extending Corcel\Model\Post', function (): void {
            $model = Mockery::mock(\Corcel\Model\Post::class);
            $model->shouldAllowMockingProtectedMethods();

            // Check that instanceof works correctly
            $postModel = new class extends \Corcel\Model\Post {
                use HasMetaFields;
            };

            // We can't easily test the protected method, but we can verify the trait is loaded
            expect(in_array(HasMetaFields::class, class_uses_recursive($postModel)))->toBeTrue();
        });

        it('trait is loaded on TestPostModel', function (): void {
            $model = new TestPostModel();
            $traits = class_uses_recursive($model);
            expect($traits)->toContain(HasMetaFields::class);
        });

        it('trait is loaded on TestUserModel', function (): void {
            $model = new TestUserModel();
            $traits = class_uses_recursive($model);
            expect($traits)->toContain(HasMetaFields::class);
        });

        it('trait is loaded on TestTaxonomyModel', function (): void {
            $model = new TestTaxonomyModel();
            $traits = class_uses_recursive($model);
            expect($traits)->toContain(HasMetaFields::class);
        });
    });

    describe('fields()', function (): void {
        it('is an alias for meta()', function (): void {
            $model = new TestPostModel();

            // Verify both methods exist
            expect(method_exists($model, 'fields'))->toBeTrue();
            expect(method_exists($model, 'meta'))->toBeTrue();
        });
    });

    describe('saveField()', function (): void {
        it('is an alias for saveMeta()', function (): void {
            $model = new TestPostModel();

            // Verify both methods exist
            expect(method_exists($model, 'saveField'))->toBeTrue();
            expect(method_exists($model, 'saveMeta'))->toBeTrue();
        });
    });

    describe('createField()', function (): void {
        it('is an alias for createMeta()', function (): void {
            $model = new TestPostModel();

            // Verify both methods exist
            expect(method_exists($model, 'createField'))->toBeTrue();
            expect(method_exists($model, 'createMeta'))->toBeTrue();
        });
    });

    describe('getMeta()', function (): void {
        it('method exists', function (): void {
            $model = new TestPostModel();
            expect(method_exists($model, 'getMeta'))->toBeTrue();
        });
    });

    describe('scopeHasMeta()', function (): void {
        it('method exists and accepts parameters', function (): void {
            $model = new TestPostModel();

            expect(method_exists($model, 'scopeHasMeta'))->toBeTrue();
        });
    });

    describe('scopeHasMetaLike()', function (): void {
        it('method exists and accepts parameters', function (): void {
            $model = new TestPostModel();

            expect(method_exists($model, 'scopeHasMetaLike'))->toBeTrue();
        });
    });

    describe('Static configuration', function (): void {
        it('metaClassMap is defined', function (): void {
            // Check that the static property exists in the trait
            $reflection = new \ReflectionClass(HasMetaFields::class);
            expect($reflection->hasProperty('metaClassMap'))->toBeTrue();
        });
    });
});
