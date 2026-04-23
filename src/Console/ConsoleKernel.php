<?php

declare(strict_types=1);

namespace Sloth\Console;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Sloth\Support\Manifest\ClassMapFinder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;

/**
 * Sloth Console Kernel.
 *
 * This class bridges WP-CLI with Laravel's Illuminate\Console\Application,
 * enabling framework, theme and app developers to write Artisan-style
 * commands available via `wp sloth`.
 *
 * ## Supported Invocation Methods
 *
 * | Method       | Input Type   | Usage                      |
 * |-------------|------------|----------------------------|
 * | handle()    | StringInput| `wp sloth inspire`          |
 * | handleArgv()| ArgvInput | `bin/sloth inspire`        |
 *
 * ## Command Discovery
 *
 * Commands are discovered from three locations:
 * 1. Framework: `src/Console/Commands/`
 * 2. App: `app/Console/`
 * 3. Theme: `theme/Console/` (if using a separate theme location)
 *
 * Each command must extend `Illuminate\Console\Command` or `Sloth\Console\Command`.
 *
 * ## Example Command
 *
 * ```php
 * class MyCommand extends \Illuminate\Console\Command
 * {
 *     protected $signature = 'my:command';
 *
 *     protected $description = 'Do something useful';
 *
 *     public function handle(): int
 *     {
 *         $this->info('Hello from Sloth!');
 *         return self::SUCCESS;
 *     }
 * }
 * ```
 *
 * ## Usage
 *
 * ```php
 * // In WP-CLI context (wp sloth *)
 * $kernel = app(ConsoleKernel::class);
 * $kernel->discoverCommands();
 * $kernel->handle(new StringInput('inspire'));
 *
 * // In standalone context (bin/sloth *)
 * $kernel = new ConsoleKernel($app);
 * $kernel->discoverCommands();
 * $kernel->handleArgv(['sloth', 'inspire']);
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Console\Command
 * @see \Sloth\Console\SlothWpCliCommand
 */
class ConsoleKernel
{
    /**
     * The underlying Illuminate console application.
     *
     * @since 1.0.0
     */
    private ConsoleApplication $console;

    /**
     * Create a new ConsoleKernel instance.
     *
     * @param Container $app The Sloth application container.
     * @since 1.0.0
     */
    public function __construct(
        private Container $app,
    ) {
        $this->console = new ConsoleApplication(
            laravel: $app,
            events: $app->make(Dispatcher::class),
            version: $app->version(),
        );

        $this->console->setName('Sloth');
        $this->console->setAutoExit(false);

        putenv('SYMFONY_CLI_DISABLE_PAGER=1');
    }

    /**
     * Handle a WP-CLI invocation using StringInput.
     *
     * This method is used by the WP-CLI integration (SlothWpCliCommand).
     * It receives a command string like "inspire" or "list" and executes it.
     *
     * ## Arguments
     *
     * @param StringInput $input A Symfony StringInput instance containing the command.
     * @return int The exit status code (0 for success, non-zero for failure).
     *
     * ## Example
     *
     * ```php
     * $kernel = app(ConsoleKernel::class);
     * $status = $kernel->handle(new StringInput('inspire'));
     * ```
     *
     * @since 1.0.0
     * @see \Sloth\Console\SlothWpCliCommand
     */
    public function handle(StringInput $input): int
    {
        $streamOutput = new StreamOutput(fopen('php://stdout', 'w'));
        \Termwind\renderUsing($streamOutput);

        return $this->console->run($input, $streamOutput);
    }

    /**
     * Handle a CLI invocation using ArgvInput.
     *
     * This method is used by the standalone bin/sloth entry point.
     * It receives the raw argv array from the command line.
     *
     * ## Arguments
     *
     * @param array $argv The command line arguments (e.g., ['sloth', 'inspire']).
     * @return int The exit status code (0 for success, non-zero for failure).
     *
     * ## Example
     *
     * ```php
     * $kernel = new ConsoleKernel($app);
     * $status = $kernel->handleArgv(['sloth', 'inspire']);
     * ```
     *
     * @since 1.0.0
     * @see bin/sloth
     */
    public function handleArgv(array $argv): int
    {
        $streamOutput = new StreamOutput(fopen('php://stdout', 'w'));
        \Termwind\renderUsing($streamOutput);

        return $this->console->run(new ArgvInput($argv), $streamOutput);
    }

    /**
     * Discover and register all commands.
     *
     * Scans the framework, app, and theme Console directories for classes
     * extending `Illuminate\Console\Command` and registers them with
     * the console application.
     *
     * Discovery order (first found, first registered):
     * 1. Framework: `src/Console/Commands/`
     * 2. App: `app/Console/`
     * 3. Theme: `theme/Console/` (if configured)
     *
     * ## Example
     *
     * ```php
     * $kernel = new ConsoleKernel($app);
     * $kernel->discoverCommands();
     * ```
     *
     * @since 1.0.0
     * @see \Sloth\Console\Command
     */
    public function discoverCommands(): void
    {
        $finder = new ClassMapFinder(Command::class);

        $paths = array_filter([
            __DIR__ . '/Commands',
            $this->app->path('Console'),
        ]);

        try {
            $themePath = $this->app->path('Console', 'theme');
            if ($themePath) {
                $paths[] = $themePath;
            }
        } catch (\Throwable) {
            // Ignore if path.theme doesn't exist
        }

        $map = $finder->find($paths);

        collect($map)
            ->keys()
            ->each(fn($commandClass) => $this->console->add(new $commandClass()));
    }
}