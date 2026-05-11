<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Context;

use Sloth\Context\Context;
use Sloth\Context\ContextProvider;

/**
 * Fixture provider that always resolves.
 */
class AlwaysProvider extends ContextProvider
{
    public function __construct(private readonly string $k, private readonly mixed $v) {}

    public function key(): string { return $this->k; }

    public function resolve(): mixed { return $this->v; }
}

/**
 * Fixture provider that never resolves.
 */
class NeverProvider extends ContextProvider
{
    public function key(): string { return 'never'; }

    public function resolve(): mixed { return 'should not appear'; }

    #[\Override]
    public function shouldResolve(): bool { return false; }
}

/**
 * Fixture provider that tracks resolution calls.
 */
class TrackingProvider extends ContextProvider
{
    public int $resolveCount = 0;

    public function key(): string { return 'tracked'; }

    public function resolve(): string
    {
        $this->resolveCount++;

        return 'resolved';
    }
}

/**
 * Helper to create a fresh Context instance.
 */
function makeContext(): Context
{
    return new Context(makeTestApp());
}

/**
 * Tests for Sloth\Context\Context.
 */
describe('Context', function (): void {

    describe('instantiation', function (): void {
        it('can be instantiated', function (): void {
            expect(makeContext())->toBeInstanceOf(Context::class);
        });

        it('implements ArrayAccess', function (): void {
            expect(makeContext())->toBeInstanceOf(\ArrayAccess::class);
        });

        it('implements IteratorAggregate', function (): void {
            expect(makeContext())->toBeInstanceOf(\IteratorAggregate::class);
        });
    });

    describe('register()', function (): void {
        it('registers a provider', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('greeting', 'hello'));

            expect($context['greeting'])->toBe('hello');
        });

        it('returns static for fluent chaining', function (): void {
            $context = makeContext();
            $result = $context->register(new AlwaysProvider('key', 'value'));

            expect($result)->toBe($context);
        });

        it('replaces an existing provider with the same key', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('key', 'first'));
            $context->register(new AlwaysProvider('key', 'second'));

            expect($context['key'])->toBe('second');
        });
    });

    describe('set()', function (): void {
        it('sets a static value', function (): void {
            $context = makeContext();
            $context->set('step', 3);

            expect($context['step'])->toBe(3);
        });

        it('returns static for fluent chaining', function (): void {
            $context = makeContext();

            expect($context->set('key', 'value'))->toBe($context);
        });

        it('static value takes precedence over provider', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('key', 'from-provider'));
            $context->set('key', 'from-static');

            expect($context['key'])->toBe('from-static');
        });
    });

    describe('ArrayAccess', function (): void {
        it('offsetGet returns provider value', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('foo', 'bar'));

            expect($context['foo'])->toBe('bar');
        });

        it('offsetGet returns null for unknown key', function (): void {
            expect(makeContext()['unknown'])->toBeNull();
        });

        it('offsetExists returns true for registered provider', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('foo', 'bar'));

            expect(isset($context['foo']))->toBeTrue();
        });

        it('offsetExists returns false when shouldResolve() is false', function (): void {
            $context = makeContext();
            $context->register(new NeverProvider());

            expect(isset($context['never']))->toBeFalse();
        });

        it('offsetGet returns null when shouldResolve() is false', function (): void {
            $context = makeContext();
            $context->register(new NeverProvider());

            expect($context['never'])->toBeNull();
        });

        it('offsetSet stores a static value', function (): void {
            $context = makeContext();
            $context['my_key'] = 'my_value';

            expect($context['my_key'])->toBe('my_value');
        });

        it('offsetUnset removes a static value', function (): void {
            $context = makeContext();
            $context->set('key', 'value');
            unset($context['key']);

            expect(isset($context['key']))->toBeFalse();
        });

        it('offsetUnset removes a provider', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('key', 'value'));
            unset($context['key']);

            expect(isset($context['key']))->toBeFalse();
        });
    });

    describe('lazy resolution', function (): void {
        it('resolves a provider only once even when accessed multiple times', function (): void {
            $context = makeContext();
            $provider = new TrackingProvider();
            $context->register($provider);
            $_ = $context['tracked'];

            expect($provider->resolveCount)->toBe(1);
        });

        it('does not resolve providers that are not accessed', function (): void {
            $context = makeContext();
            $provider = new TrackingProvider();
            $context->register($provider);

            // Never access 'tracked'
            expect($provider->resolveCount)->toBe(0);
        });
    });

    describe('toArray()', function (): void {
        it('returns all resolved values as a plain array', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('foo', 'bar'));
            $context->register(new NeverProvider());
            $context->set('baz', 'qux');

            $array = $context->toArray();

            expect($array)->toBeArray();
            expect($array['foo'])->toBe('bar');
            expect($array['baz'])->toBe('qux');
            expect(array_key_exists('never', $array))->toBeFalse();
        });
    });

    describe('getContext()', function (): void {
        it('returns $this for backwards compatibility', function (): void {
            $context = makeContext();

            expect($context->getContext())->toBe($context);
        });
    });

    describe('IteratorAggregate', function (): void {
        it('is iterable and yields resolved values', function (): void {
            $context = makeContext();
            $context->register(new AlwaysProvider('key', 'value'));

            $result = [];
            foreach ($context as $k => $v) {
                $result[$k] = $v;
            }

            expect($result['key'])->toBe('value');
        });
    });
});
