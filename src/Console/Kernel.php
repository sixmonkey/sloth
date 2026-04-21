<?php

declare(strict_types=1);

namespace Sloth\Console;

use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Sloth\Core\Application;
use Sloth\Support\Manifest\ClassMapFinder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\StreamOutput;
use function Termwind\renderUsing;

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
        $output = new StreamOutput(fopen('php://stdout', 'w'));
        renderUsing($output);

        $this->console = new ConsoleApplication(
            laravel: $app,
            events: $app->make('events'),
            version: $app->version(),
        );

        $this->console->setName('Sloth');
        $this->console->setAutoExit(false);

        // Disable Symfony Console pager — less may not be available in all environments.
        putenv('SYMFONY_CLI_DISABLE_PAGER=1');
    }

    /**
     * Handle a WP-CLI invocation.
     *
     * The first element of $argv is treated as the script name by Symfony Console —
     * only the remaining elements are parsed as command + arguments.
     *
     * @param array<int, string>   $args       Positional arguments from WP-CLI.
     * @param array<string, mixed> $assoc_args Named arguments from WP-CLI (--flag=value).
     * @since 1.0.0
     */
    public function handle(array $args, array $assoc_args): void
    {
        // 'sloth' is the script name (argv[0]) — Symfony Console ignores it.
        // The actual command name starts at argv[1].
        $argv = array_merge(['sloth'], $args);

        foreach ($assoc_args as $key => $value) {
            $argv[] = $value === true ? "--{$key}" : "--{$key}={$value}";
        }

        $streamOutput = new StreamOutput(fopen('php://stdout', 'w'));
        renderUsing($streamOutput);
        $this->console->run(new ArgvInput($argv), $streamOutput);
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
