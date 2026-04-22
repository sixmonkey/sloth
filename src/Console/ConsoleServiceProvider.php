<?php

declare(strict_types=1);

namespace Sloth\Console;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for WP-CLI integration.
 *
 * Registers the Sloth console kernel and exposes all discovered commands
 * under the `wp sloth` WP-CLI namespace.
 *
 * Only active when WP-CLI is running — zero overhead on web requests.
 *
 * @since 1.0.0
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /**
     * Register the console kernel.
     *
     * Only registers when WP-CLI is active.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        $this->app->singleton(
            Kernel::class,
            fn($app) => new Kernel($app),
        );
    }

    /**
     * Register the `wp sloth` WP-CLI command.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if (!defined('WP_CLI') || !WP_CLI) {
            return;
        }

        \WP_CLI::add_command('sloth', SlothCommand::class);
    }
}
