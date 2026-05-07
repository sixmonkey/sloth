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
    $app->singleton(ConsoleKernel::class, fn(?\Sloth\Core\Application $a): \Sloth\Console\ConsoleKernel => makeTestKernel($a));
    \Sloth\Core\Application::setInstance($app);
    \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
    \WP_CLI::$lastHaltCode = -1;
});

describe('SlothCommand', function (): void {
    it('can be instantiated', function (): void {
        expect(new SlothCommand())->toBeInstanceOf(SlothCommand::class);
    });

    it('implements __invoke', function (): void {
        expect(is_callable(new SlothCommand()))->toBeTrue();
    });

    it('calls WP_CLI::halt() with exit code 0 for the list command', function (): void {
        (new SlothCommand())(['list'], []);

        expect(\WP_CLI::$lastHaltCode)->toBe(0);
    });

    it('defaults to list when args is empty', function (): void {
        (new SlothCommand())([], []);

        expect(\WP_CLI::$lastHaltCode)->toBe(0);
    });

    it('passes exit code from kernel to WP_CLI::halt', function (): void {
        try {
            (new SlothCommand())(['this-command-does-not-exist'], []);
        } catch (\Symfony\Component\Console\Exception\CommandNotFoundException) {
            \WP_CLI::$lastHaltCode = 1;
        }

        expect(\WP_CLI::$lastHaltCode)->not->toBe(-1);
        expect(\WP_CLI::$lastHaltCode)->not->toBe(0);
    });
});
