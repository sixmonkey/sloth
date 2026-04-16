<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Utility;

use Sloth\Utility\ClassResolver\ClassResolver;
use ReflectionClass;

/**
 * Unit tests for the ClassResolver abstract class.
 *
 * Tests verify that:
 * - Subclasses can define their own configuration
 * - Static properties are properly inherited
 */
describe('ClassResolver', function (): void {
    /**
     * Concrete implementation for testing.
     */
    class ConcreteResolver extends ClassResolver
    {
        protected static string $dir = 'Test';
        protected static string $cacheKey = 'test.resolver';
        protected static string $subclassOf = \ArrayObject::class;
    }

    describe('Static configuration', function (): void {
        it('allows subclasses to define custom directory', function (): void {
            $reflection = new ReflectionClass(ConcreteResolver::class);
            $prop = $reflection->getProperty('dir');
            expect($prop->getValue())->toBe('Test');
        });

        it('allows subclasses to define custom cache key', function (): void {
            $reflection = new ReflectionClass(ConcreteResolver::class);
            $prop = $reflection->getProperty('cacheKey');
            expect($prop->getValue())->toBe('test.resolver');
        });

        it('allows subclasses to define custom base class', function (): void {
            $reflection = new ReflectionClass(ConcreteResolver::class);
            $prop = $reflection->getProperty('subclassOf');
            expect($prop->getValue())->toBe(\ArrayObject::class);
        });
    });
});
