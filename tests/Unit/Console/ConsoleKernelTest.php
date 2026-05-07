<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Sloth\Console\ConsoleKernel;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Tests for Sloth\Console\ConsoleKernel.
 *
 * @since 1.0.0
 */
describe('ConsoleKernel', function (): void {
    describe('construction', function (): void {
        it('can be instantiated with an Application', function (): void {
            expect(makeTestKernel())->toBeInstanceOf(ConsoleKernel::class);
        });

        it('returns an instance of ConsoleKernel', function (): void {
            expect(makeTestKernel())->toBeInstanceOf(ConsoleKernel::class);
        });

        it('registers a VarDumper CliDumper handler on construction', function (): void {
            makeTestKernel();

            $handler = VarDumper::setHandler(null);
            expect($handler)->toBeCallable();

            VarDumper::setHandler($handler);
        });
    });

    describe('discoverCommands()', function (): void {
        it('returns static for fluent chaining', function (): void {
            $kernel = makeTestKernel();

            expect($kernel->discoverCommands())->toBe($kernel);
        });

        it('discovers framework commands without throwing', function (): void {
            expect(fn() => makeTestKernel()->discoverCommands())
                ->not()->toThrow(\Throwable::class);
        });

        it('does not throw when app/Console/ does not exist', function (): void {
            expect(fn() => makeTestKernel()->discoverCommands())
                ->not()->toThrow(\Throwable::class);
        });
    });

    describe('handle()', function (): void {
        it('returns 0 for the list command', function (): void {
            $status = makeTestKernel()->discoverCommands()->handle(['list'], []);

            expect($status)->toBe(0);
        });

        it('returns non-zero for an unknown command', function (): void {
            try {
                $status = makeTestKernel()->discoverCommands()
                    ->handle(['this-command-does-not-exist'], []);
            } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
                $status = 1;
            }

            expect($status)->not()->toBe(0);
        });

        it('passes assocArgs as flags', function (): void {
            $status = makeTestKernel()->discoverCommands()->handle(['list'], ['help' => true]);

            expect($status)->toBe(0);
        });
    });

    describe('handleArgv()', function (): void {
        it('returns 0 for the inspire command', function (): void {
            $status = makeTestKernel()->discoverCommands()->handleArgv(['sloth', 'inspire']);

            expect($status)->toBe(0);
        });

        it('returns 0 for the list command', function (): void {
            $status = makeTestKernel()->discoverCommands()->handleArgv(['sloth', 'list']);

            expect($status)->toBe(0);
        });

        it('returns non-zero for an unknown command', function (): void {
            try {
                $status = makeTestKernel()->discoverCommands()
                    ->handleArgv(['sloth', 'this-command-does-not-exist']);
            } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
                $status = 1;
            }

            expect($status)->not()->toBe(0);
        });
    });

    describe('chaining', function (): void {
        it('supports discoverCommands()->handle() as a fluent chain returning an integer', function (): void {
            $result = makeTestKernel()->discoverCommands()->handle(['list'], []);

            expect($result)->toBeInt();
        });

        it('supports discoverCommands()->handleArgv() as a fluent chain returning an integer', function (): void {
            $result = makeTestKernel()->discoverCommands()->handleArgv(['sloth', 'list']);

            expect($result)->toBeInt();
        });
    });
});
