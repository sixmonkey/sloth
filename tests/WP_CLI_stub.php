<?php

declare(strict_types=1);

/**
 * WP_CLI stub for testing SlothCommand.
 *
 * @since 1.0.0
 */
class WP_CLI
{
    public static int $lastHaltCode = -1;

    public static function halt(int $code): void
    {
        static::$lastHaltCode = $code;
    }
}
