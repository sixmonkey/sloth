<?php

declare(strict_types=1);
namespace Sloth\Console;

use WP_CLI;

/**
 * WP-CLI command handler for `wp sloth`.
 *
 * Acts as the entry point for all `wp sloth <command>` invocations.
 * Discovers framework, app and theme commands and delegates to the
 * Illuminate console application via ConsoleKernel.
 *
 * @since 1.0.0
 * @see ConsoleKernel
 * @see ConsoleServiceProvider
 */
class SlothCommand
{
    /**
     * Handle a `wp sloth <command>` invocation.
     *
     * WP-CLI calls __invoke for any `wp sloth *` command.
     * Defaults to `list` when no subcommand is given.
     *
     * @param array<int, string>   $args       positional arguments
     * @param array<string, mixed> $assoc_args named arguments (--flag=value)
     *
     * @since 1.0.0
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        if ($args === []) {
            $args = ['list'];
        }

        $status = app(ConsoleKernel::class)
            ->discoverCommands()
            ->handle($args, $assoc_args)
        ;

        WP_CLI::halt($status);
    }
}
