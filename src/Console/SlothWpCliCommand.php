<?php

declare(strict_types=1);

namespace Sloth\Console;

use Symfony\Component\Console\Input\StringInput;

/**
 * WP-CLI command handler for `wp sloth`.
 *
 * This class bridges WP-CLI to the Sloth ConsoleKernel, allowing
 * commands to be executed via `wp sloth <command>`.
 *
 * ## Usage
 *
 * ```bash
 * wp sloth list
 * wp sloth inspire
 * wp sloth manifest:clear
 * wp sloth config:get theme.option
 * ```
 *
 * ## How It Works
 *
 * 1. WP-CLI calls this command when `wp sloth` is invoked
 * 2. Arguments are converted to a command string (e.g., "inspire")
 * 3. Sloth\Console\ConsoleKernel handles the command
 * 4. Exit status is passed back to WP_CLI via WP_CLI::halt()
 *
 * ## Arguments
 *
 * @param array $args Positional arguments passed to the command.
 * @param array $assoc_args Named arguments (flags) passed to the command.
 *
 * ## Example
 *
 * ```php
 * // When running: wp sloth inspire
 * $args = ['inspire'];
 * $assoc_args = [];
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Console\ConsoleKernel
 */
class SlothWpCliCommand
{
    /**
     * Handle the WP-CLI invocation.
     *
     * This method is called by WP-CLI when the `wp sloth` command is executed.
     * It converts the arguments to a command string and passes it to
     * the ConsoleKernel for execution.
     *
     * @param array $args Positional arguments (e.g., ['inspire', '--verbose']).
     * @param array $assoc_args Named arguments like ['verbose' => true].
     * @return void This method exits via WP_CLI::halt().
     *
     * @since 1.0.0
     * @see \Sloth\Console\ConsoleKernel::handle()
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        if (empty($args)) {
            $args = ['list'];
        }

        $command = implode(' ', $args);

        foreach ($assoc_args as $key => $value) {
            $command .= $value === true
                ? " --{$key}"
                : " --{$key}={$value}";
        }

        $kernel = app(ConsoleKernel::class);
        $kernel->discoverCommands();

        $status = $kernel->handle(new StringInput($command));

        WP_CLI::halt($status);
    }
}