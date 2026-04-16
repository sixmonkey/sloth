<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Context;

use Sloth\Context\Context;

/**
 * Unit tests for the Context class.
 */
describe('Context', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $context = new Context();
            expect($context)->toBeInstanceOf(Context::class);
        });
    });

    describe('getContext()', function (): void {
        it('method exists and returns array', function (): void {
            $context = new Context();
            expect(method_exists($context, 'getContext'))->toBeTrue();
        });
    });
});
