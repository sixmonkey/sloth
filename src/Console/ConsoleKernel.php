<?php

declare(strict_types=1);
namespace Sloth\Console;

use function Termwind\renderUsing;
use Exception;
use Illuminate\Console\Application as ConsoleApplication;
use Illuminate\Console\Command;
use Sloth\Core\Application;
use Sloth\Support\Manifest\ClassMapFinder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;

/**
 * Sloth Console Kernel.
 *
 * Bridges WP-CLI with Laravel's Illuminate\Console\Application,
 * allowing framework, theme and app developers to write Artisan-style
 * commands available via `wp sloth`.
 *
 * ## Entry Points
 *
 * | Method            | Context           | Usage                      |
 * |-------------------|-------------------|----------------------------|
 * | handle()          | WP-CLI            | `wp sloth inspire`          |
 * | handleArgv()      | Standalone CLI    | `bin/sloth inspire`        |
 *
 * ## Command Discovery
 *
 * Commands are discovered from three locations:
 * 1. Framework: `src/Console/Commands/`
 * 2. App: `app/Console/`
 * 3. Theme: `theme/Console/` (if using a separate theme location)
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
 * $status = $kernel->discoverCommands()->handle(['inspire'], []);
 *
 * // In standalone context (bin/sloth *)
 * $kernel = new ConsoleKernel($app);
 * $status = $kernel->discoverCommands()->handleArgv(['sloth', 'inspire']);
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Console\Command
 * @see SlothCommand
 * @see ConsoleServiceProvider
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
     * @param Application $app the Sloth application container
     *
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

        putenv('SYMFONY_CLI_DISABLE_PAGER=1');

        // Register CLI-appropriate VarDumper handler for terminal output.
        // ConsoleKernel is only instantiated in CLI contexts, so this
        // handler never interferes with the web DebugBar handler.
        VarDumper::setHandler(static function (mixed $var): void {
            $cloner = new VarCloner();
            $dumper = new CliDumper();
            $dumper->dump($cloner->cloneVar($var));
        });
    }

    /**
     * Handle a WP-CLI invocation.
     *
     * Receives positional and named arguments from WP-CLI and executes
     * the corresponding console command.
     *
     * @param  array<int, string>   $args      positional arguments from WP-CLI
     * @param  array<string, mixed> $assocArgs named arguments from WP-CLI (--flag=value)
     * @return int                  the exit status code (0 for success, non-zero for failure)
     *
     * @since 1.0.0
     */
    public function handle(array $args, array $assocArgs = []): int
    {
        $argv = ['sloth'];

        foreach ($args as $arg) {
            $argv[] = $arg;
        }

        foreach ($assocArgs as $key => $value) {
            $argv[] = $value === true ? "--{$key}" : "--{$key}={$value}";
        }

        return $this->run($argv);
    }

    /**
     * Handle a CLI invocation using ArgvInput.
     *
     * This method is used by the standalone bin/sloth entry point.
     * It receives the raw argv array from the command line.
     *
     * @param  array<int, string> $argv The command line arguments (e.g., ['sloth', 'inspire']).
     * @return int                the exit status code (0 for success, non-zero for failure)
     *
     * @since 1.0.0
     * @see bin/sloth
     */
    public function handleArgv(array $argv): int
    {
        return $this->run($argv);
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
     * Missing directories are silently skipped.
     *
     * @return static the kernel instance for fluent chaining
     *
     * @since 1.0.0
     * @see \Sloth\Console\Command
     */
    public function discoverCommands(): static
    {
        $finder = new ClassMapFinder(Command::class);

        $paths = array_filter([__DIR__ . '/Commands'], 'is_dir');

        try {
            $appPath = $this->app->path('Console');

            if (is_dir($appPath)) {
                $paths[] = $appPath;
            }
        } catch (Throwable) {
            // path.app not registered — skip silently
        }

        try {
            $themePath = $this->app->path('Console', 'theme');

            if ($themePath && is_dir($themePath)) {
                $paths[] = $themePath;
            }
        } catch (Throwable) {
            // path.theme not registered — skip silently
        }

        $map = $finder->find($paths);

        collect($map)
            ->keys()
            ->each(fn ($commandClass) => $this->console->add(new $commandClass()))
        ;

        return $this;
    }

    /**
     * Run the console application with the given argv array.
     *
     * Centralizes StreamOutput creation and Termwind renderUsing call
     * for both handle() and handleArgv().
     *
     * @param array<int, string> $argv   the command line arguments
     * @param ?OutputInterface   $output
     *
     * @throws Exception
     *
     * @return int the exit status code (0 for success, non-zero for failure)
     *
     * @since 1.0.0
     */
    protected function run(array $argv, ?OutputInterface $output = null): int
    {
        $output ??= new StreamOutput(fopen('php://stdout', 'w'));
        renderUsing($output);

        return $this->console->run(new ArgvInput($argv), $output);
    }
}
