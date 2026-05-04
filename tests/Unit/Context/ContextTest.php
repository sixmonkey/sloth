<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use Sloth\Context\Context;

/**
 * Unit tests for the Context class.
 */
describe('Context', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated with an app', function (): void {
            $app = $this->createMock(\Sloth\Core\Application::class);
            $context = new Context($app);
            expect($context)->toBeInstanceOf(Context::class);
        });
    });

    describe('getContext()', function (): void {
        it('method exists', function (): void {
            $app = $this->createMock(\Sloth\Core\Application::class);
            $context = new Context($app);
            expect(method_exists($context, 'getContext'))->toBeTrue();
        });
    });
});
