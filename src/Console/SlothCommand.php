<?php

declare(strict_types=1);

namespace Sloth\Console;

/**
 * WP-CLI command handler for `wp sloth`.
 *
 * Acts as the entry point for all `wp sloth <command>` invocations.
 * Discovers framework, app and theme commands and delegates to the
 * Illuminate console application.
 *
 * @since 1.0.0
 */
class SlothCommand
{
    /**
     * Handle a `wp sloth <command>` invocation.
     *
     * WP-CLI calls __invoke for any `wp sloth *` command.
     * Defaults to `list` when no subcommand is given.
     *
     * @param array<int, string>   $args       Positional arguments.
     * @param array<string, mixed> $assoc_args Named arguments (--flag=value).
     * @since 1.0.0
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        if (empty($args)) {
            $args = ['list'];
        }

        $kernel = app(Kernel::class);
        $kernel->discoverCommands();
        $kernel->handle($args, $assoc_args);
    }
}
