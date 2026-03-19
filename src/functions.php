<?php

declare(strict_types=1);

use Tracy\Debugger;

/**
 * Debug Helper Function
 *
 * Provides a shortcut to Tracy's barDump function for
 * quick debugging in development.
 *
 * @since 1.0.0
 *
 * @param mixed ...$vars Variables to dump
 *
 * @return mixed Returns the first variable unchanged (allows inline debugging)
 *
 * @example
 * ```php
 * // Simple debug
 * debug($variable);
 *
 * // Inline debug
 * $result = debug($data);
 *
 * // Multiple variables
 * debug($var1, $var2, $var3);
 * ```
 *
 * @tracySkipLocation
 */
if (!function_exists('debug')) {
    /**
     * Dumps variables to Tracy bar for debugging.
     *
     * @param mixed ...$vars Variables to dump
     *
     * @return mixed Returns the first variable unchanged
     */
    function debug(mixed ...$vars): mixed
    {
        if (class_exists(Debugger::class)) {
            foreach ($vars as $var) {
                Debugger::barDump($var);
            }
        }

        return $vars[0] ?? null;
    }
}
