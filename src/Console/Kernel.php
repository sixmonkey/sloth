<?php

declare(strict_types=1);

namespace Sloth\Console;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Sloth\Core\Application;
use Sloth\Support\Manifest\ClassMapFinder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Sloth Console Kernel.
 *
 * Bridges WP-CLI with Laravel's Illuminate\Console\Application,
 * allowing framework, theme and app developers to write Artisan-style
 * commands available via `wp sloth`.
 *
 * ## Discovery order
 *
 * 1. Framework commands — src/Console/Commands/
 * 2. App commands       — app/Console/
 * 3. Theme commands     — theme/Console/
 *
 * ## Usage
 *
 * Create a command in app/Console/:
 *
 * ```php
 * class MyClearCommand extends \Illuminate\Console\Command
 * {
 *     protected $signature   = 'my:clear';
 *     protected $description = 'Do something useful';
 *
 *     public function handle(): int
 *     {
 *         $this->info('Done!');
 *         return self::SUCCESS;
 *     }
 * }
 * ```
 *
 * Then run: `wp sloth my:clear`
 *
 * @since 1.0.0
 */
class Kernel
{
    /**
     * The underlying Illuminate console application.
     *
     * @since 1.0.0
     */
    private ConsoleApplication $console;

    /**
     * @param Application $app The Sloth application container.
     * @since 1.0.0
     */
    public function __construct(private Application $app)
    {
        $this->console = new ConsoleApplication(
            laravel: $app,
            events: $app->make('events'),
            version: $app->version(),
        );

        $this->console->setName('Sloth');
        $this->console->setAutoExit(false);
    }

    /**
     * Handle a WP-CLI invocation.
     *
     * Rebuilds argv-style input from WP-CLI args and passes it through
     * to the Illuminate console application.
     *
     * @param array<int, string>    $args       Positional arguments from WP-CLI.
     * @param array<string, mixed>  $assoc_args Associative arguments from WP-CLI.
     * @since 1.0.0
     */
    public function handle(array $args, array $assoc_args): void
    {
        $argv = array_merge(['wp sloth'], $args);

        foreach ($assoc_args as $key => $value) {
            $argv[] = $value === true ? "--{$key}" : "--{$key}={$value}";
        }

        $this->console->run(new ArgvInput($argv), new ConsoleOutput());
    }

    /**
     * Register a single command with the console application.
     *
     * @param Command $command
     * @since 1.0.0
     */
    public function add(Command $command): void
    {
        $this->console->add($command);
    }

    /**
     * Discover and register all commands.
     *
     * Scans framework, app and theme Console directories for classes
     * extending Illuminate\Console\Command.
     *
     * @since 1.0.0
     */
    public function discoverCommands(): void
    {
        $finder = new ClassMapFinder(Command::class);

        $map = $finder->find([
            __DIR__ . '/Commands',
            app()->path('Console'),
            app()->path('Console', 'theme'),
        ]);

        collect($map)
            ->keys()
            ->each(fn($commandClass) => $this->console->add(new $commandClass()));
    }
}
