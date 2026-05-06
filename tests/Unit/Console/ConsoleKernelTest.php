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
            $app = makeTestApp();

            $kernel = new ConsoleKernel($app);

            expect($kernel)->toBeInstanceOf(ConsoleKernel::class);
        });

        it('returns an instance of ConsoleKernel', function (): void {
            $app = makeTestApp();

            $kernel = new ConsoleKernel($app);

            expect($kernel)->toBeInstanceOf(ConsoleKernel::class);
        });

        it('registers a VarDumper CliDumper handler on construction', function (): void {
            $app = makeTestApp();

            $kernel = new ConsoleKernel($app);

            // VarDumper::setHandler(null) returns the previously registered handler
            $handler = VarDumper::setHandler(null);
            expect($handler)->toBeCallable();

            // Restore a default handler so subsequent tests work
            VarDumper::setHandler($handler);
        });
    });

    describe('discoverCommands()', function (): void {
        it('returns static for fluent chaining', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            $result = $kernel->discoverCommands();

            expect($result)->toBe($kernel);
        });

        it('discovers framework commands without throwing', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            expect(fn() => $kernel->discoverCommands())->not()->toThrow(\Throwable::class);
        });

        it('does not throw when app/Console/ does not exist', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            // sys_get_temp_dir() has no Console subdirectory
            expect(fn() => $kernel->discoverCommands())->not()->toThrow(\Throwable::class);
        });
    });

    describe('handle()', function (): void {
        it('returns 0 for the list command', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            ob_start();
            $status = $kernel->discoverCommands()->handle(['list'], []);
            ob_end_clean();

            expect($status)->toBe(0);
        });

        it('returns non-zero for an unknown command', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            try {
                $status = $kernel->discoverCommands()->handle(['this-command-does-not-exist'], []);
            } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
                $status = 1;
            }

            expect($status)->not()->toBe(0);
        });

        it('passes assocArgs as flags', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            ob_start();
            $status = $kernel->discoverCommands()->handle(['list'], ['help' => true]);
            ob_end_clean();

            expect($status)->toBe(0);
        });
    });

    describe('handleArgv()', function (): void {
        it('returns 0 for the inspire command', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            ob_start();
            $status = $kernel->discoverCommands()->handleArgv(['sloth', 'inspire']);
            ob_end_clean();

            expect($status)->toBe(0);
        });

        it('returns 0 for the list command', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            ob_start();
            $status = $kernel->discoverCommands()->handleArgv(['sloth', 'list']);
            ob_end_clean();

            expect($status)->toBe(0);
        });

        it('returns non-zero for an unknown command', function (): void {
            $app = makeTestApp();
            $kernel = new ConsoleKernel($app);

            try {
                $status = $kernel->discoverCommands()->handleArgv(['sloth', 'this-command-does-not-exist']);
            } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
                $status = 1;
            }

            expect($status)->not()->toBe(0);
        });
    });

    describe('chaining', function (): void {
        it('supports discoverCommands()->handle() as a fluent chain returning an integer', function (): void {
            $app = makeTestApp();

            ob_start();
            $result = new ConsoleKernel($app)->discoverCommands()->handle(['list'], []);
            ob_end_clean();

            expect($result)->toBeInt();
        });

        it('supports discoverCommands()->handleArgv() as a fluent chain returning an integer', function (): void {
            $app = makeTestApp();

            ob_start();
            $result = new ConsoleKernel($app)->discoverCommands()->handleArgv(['sloth', 'list']);
            ob_end_clean();

            expect($result)->toBeInt();
        });
    });
});
