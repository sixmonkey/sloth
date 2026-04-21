<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\ACF;

use Sloth\ACF\AcfProxy;

/**
 * Unit tests for the AcfProxy class.
 */
describe('AcfProxy', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated with array', function (): void {
            $proxy = new AcfProxy(['field1' => 'value1']);
            expect($proxy)->toBeInstanceOf(AcfProxy::class);
        });

        it('can be instantiated with empty array', function (): void {
            $proxy = new AcfProxy([]);
            expect($proxy)->toBeInstanceOf(AcfProxy::class);
        });
    });

    describe('__call()', function (): void {
        it('returns value for existing field', function (): void {
            $proxy = new AcfProxy(['title' => 'Hello World']);
            $result = $proxy->title();

            expect($result)->toBe('Hello World');
        });

        it('returns null for non-existing field', function (): void {
            $proxy = new AcfProxy(['title' => 'Hello']);
            $result = $proxy->nonExistent();

            expect($result)->toBeNull();
        });

        it('handles multiple fields', function (): void {
            $proxy = new AcfProxy([
                'title' => 'My Title',
                'content' => 'My Content',
                'count' => 42,
            ]);

            expect($proxy->title())->toBe('My Title');
            expect($proxy->content())->toBe('My Content');
            expect($proxy->count())->toBe(42);
        });
    });
});
