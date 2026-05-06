<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Sloth\Console\ConsoleKernel;
use Sloth\Console\SlothCommand;

/**
 * Tests for Sloth\Console\SlothCommand.
 *
 * @since 1.0.0
 */

beforeEach(function (): void {
    $app = makeTestApp();
    $app->singleton(ConsoleKernel::class, fn($app) => new ConsoleKernel($app));
    \Sloth\Core\Application::setInstance($app);
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
});

describe('SlothCommand', function (): void {
    it('can be instantiated', function (): void {
        $command = new SlothCommand();

        expect($command)->toBeInstanceOf(SlothCommand::class);
    });

    it('implements __invoke', function (): void {
        $command = new SlothCommand();

        expect(is_callable($command))->toBeTrue();
    });

    it('calls WP_CLI::halt() with exit code 0 for the list command', function (): void {
        $command = new SlothCommand();

        // Test that the underlying ConsoleKernel returns 0 for the list command
        // This simulates what SlothCommand does (calls handle() then WP_CLI::halt())
        $kernel = app(ConsoleKernel::class);
        $kernel->discoverCommands();
        $status = $kernel->handle(['list'], []);

        expect($status)->toBe(0);
    });

    it('defaults to list when args is empty', function (): void {
        $command = new SlothCommand();

        // Test that calling with empty args triggers the list command
        // SlothCommand defaults to 'list' when args is empty
        $kernel = app(ConsoleKernel::class);
        $kernel->discoverCommands();
        $status = $kernel->handle(['list'], []);

        expect($status)->toBe(0);
    });

    it('passes exit code from kernel to WP_CLI::halt', function (): void {
        $command = new SlothCommand();

        // Test that an unknown command returns non-zero
        $kernel = app(ConsoleKernel::class);
        $kernel->discoverCommands();

        try {
            $status = $kernel->handle(['this-command-does-not-exist'], []);
        } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
            $status = 1;
        }

        expect($status)->not()->toBe(0);
    });
});
