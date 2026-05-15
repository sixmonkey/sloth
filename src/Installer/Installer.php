<?php

declare(strict_types=1);
namespace Sloth\Installer;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Deprecated;

/**
 * Installer class for setting up the Sloth WordPress theme.
 *
 * This class serves as the Composer script entrypoint. The two public static
 * methods (config, config_quiet) are registered in composer.json and called
 * by Composer during post-install/post-update.
 *
 * @deprecated 2.0 The Installer is no longer needed. Remove the post-install-cmd
 *             and post-update-cmd scripts from your composer.json.
 *             See https://docs.folivoro.com/upgrade#infrastructure-files-now-owned-by-the-project
 * @since 1.0.0
 */
class Installer
{
    /**
     * Composer IO interface used for all terminal interaction.
     *
     * @since 1.0.0
     */
    private readonly IOInterface $io;

    // -------------------------------------------------------------------------
    // Public Composer script entrypoints
    // -------------------------------------------------------------------------
    /**
     * Primary Composer script entrypoint.
     *
     * @param Event $event the Composer script event injected by Composer
     *
     * @since 1.0.0
     */
    #[Deprecated(message: '2.0 Remove this from your composer.json scripts.')]
    public static function config(Event $event): void
    {
        new self($event)->run();
    }

    /**
     * Silent Composer script entrypoint.
     *
     * @param Event $event the Composer script event injected by Composer
     *
     * @since 1.0.0
     */
    #[Deprecated(message: '2.0 Remove this from your composer.json scripts.')]
    public static function config_quiet(Event $event): void
    {
        new self($event)->run();
    }

    // -------------------------------------------------------------------------
    // Constructor & core flow
    // -------------------------------------------------------------------------

    /**
     * @param Event $event the Composer script event
     *
     * @since 1.0.0
     */
    private function __construct(Event $event)
    {
        $this->io = $event->getIO();
    }

    /**
     * Output a deprecation notice — the Installer is a no-op in 2.0.
     *
     * @since 2.0.0
     */
    private function run(): void
    {
        $this->io->write([
            '',
            '<warning>⚠️  Sloth 2.0: The Installer is no longer needed.</warning>',
            '<info>   You can safely remove the post-install-cmd and post-update-cmd</info>',
            '<info>   scripts from your composer.json.</info>',
            '<info>   See https://docs.folivoro.com/upgrade#infrastructure-files-now-owned-by-the-project</info>',
            '',
        ]);
    }
}
