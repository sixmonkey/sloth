<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Sloth\Console\Command;
use Sloth\Console\ConsoleKernel;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Fixture command for testing provider-registered commands.
 */
class ProviderRegisteredCommand extends Command
{
    protected $signature = 'provider:test';

    protected $description = 'A command registered via a service provider';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

/**
 * Second fixture command for testing multiple provider-registered commands.
 */
class AnotherProviderCommand extends Command
{
    protected $signature = 'provider:other';

    protected $description = 'Another command registered via a service provider';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}

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
            expect(fn(): \Sloth\Console\ConsoleKernel => makeTestKernel()->discoverCommands())
                ->not()->toThrow(\Throwable::class);
        });

        it('does not throw when app/Console/ does not exist', function (): void {
            expect(fn(): \Sloth\Console\ConsoleKernel => makeTestKernel()->discoverCommands())
                ->not()->toThrow(\Throwable::class);
        });

        describe('provider-registered commands', function (): void {
            it('registers a command tagged via $this->commands() in a service provider', function (): void {
                // Simulate a service provider calling $this->commands([MyCommand::class])
                // which tags the command class as 'commands' in the container.
                $app = makeTestApp();
                $app->tag([ProviderRegisteredCommand::class], 'commands');

                $status = makeTestKernel($app)->discoverCommands()
                    ->handle(['provider:test'], []);

                expect($status)->toBe(0);
            });

            it('registers multiple commands tagged via $this->commands()', function (): void {
                $app = makeTestApp();
                $app->tag([ProviderRegisteredCommand::class, AnotherProviderCommand::class], 'commands');

                $kernel = makeTestKernel($app)->discoverCommands();

                // Both commands should be available
                expect(fn(): int => $kernel->handle(['provider:test'], []))->not()->toThrow(\Throwable::class);
                expect(fn(): int => $kernel->handle(['provider:other'], []))->not()->toThrow(\Throwable::class);
            });

            it('accepts an already-instantiated command object', function (): void {
                $app = makeTestApp();

                // Tag an instance rather than a class name
                $app->instance('provider.command.instance', new ProviderRegisteredCommand());
                $app->tag(['provider.command.instance'], 'commands');

                $status = makeTestKernel($app)->discoverCommands()
                    ->handle(['provider:test'], []);

                expect($status)->toBe(0);
            });
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
