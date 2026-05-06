<?php

declare(strict_types=1);

namespace Sloth\Debug\CollectorProviders;

use DebugBar\DebugBarException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Message Collector Provider.
 *
 * Configures the MessagesCollector for the DebugBar and registers
 * a PHP error handler that routes notices/warnings to the DebugBar
 * while letting critical errors propagate as exceptions.
 *
 * @since 1.0.0
 */
class MessageCollectorProvider extends AbstractCollectorProvider
{
    /**
     * Bitmask of critical error levels that should be converted
     * to exceptions and handled by the ExceptionHandler (Whoops).
     *
     * All other error levels (notices, warnings, deprecated, etc.)
     * are logged to the DebugBar MessagesCollector instead.
     *
     * @since 1.0.0
     */
    private const CRITICAL_ERRORS = E_ERROR
        | E_PARSE
        | E_CORE_ERROR
        | E_COMPILE_ERROR
        | E_USER_ERROR
        | E_RECOVERABLE_ERROR;

    /**
     * Register and configure the MessagesCollector for debug bar in sloth.
     *
     * Sets up message collection with file traces, editor integration,
     * VarDumper override, and a PHP error handler that routes
     * non-critical errors to the DebugBar.
     *
     * In CLI context neither the VarDumper handler nor the error handler
     * are registered — the ExceptionServiceProvider's handlers remain
     * active and route errors to the terminal via renderForConsole().
     *
     * @return void
     * @throws DebugBarException|BindingResolutionException
     * @since 1.0.0
     */
    public function boot(): void
    {
        $messageCollector = $this->debugBar->getMessagesCollector();
        $messageCollector->setTimeDataCollector($this->debugBar->getTimeCollector());

        $messageCollector->collectFileTrace(true);
        $messageCollector->addBacktraceExcludePaths([
            '/src/',
        ]);
        $messageCollector->setEditorLinkTemplate(config('debugger.editor', 'phpstorm'));

        $isCli = false;
        try {
            $isCli = app(\Sloth\Http\RequestContext::class)->isCli();
        } catch (\Throwable) {
            // RequestContext not yet available — default to not CLI
        }

        if (!$isCli) {
            $originalHandler = VarDumper::setHandler(function ($var) use (&$originalHandler, $messageCollector): void {
                if ($originalHandler && !config('debugger.bar.dump_all', false)) {
                    $originalHandler($var);
                }
                $messageCollector->addMessage($var);
            });
        }

        if (!$isCli) {
            $this->registerErrorHandler($messageCollector);
        }

        $this->addCollector($messageCollector);
    }

    /**
     * Register a PHP error handler that separates critical errors from
     * notices/warnings.
     *
     * Critical errors (E_ERROR, E_PARSE, etc.) are thrown as
     * ErrorException so they can be caught by the ExceptionHandler
     * and rendered with Whoops.
     *
     * All other error levels (notices, warnings, deprecated, strict,
     * etc.) are collected as DebugBar messages — visible in the
     * Messages tab but non-blocking.
     *
     * @param \DebugBar\DataCollector\MessagesCollector $messageCollector
     * @since 1.0.0
     */
    protected function registerErrorHandler($messageCollector): void
    {
        set_error_handler(function (
            int $severity,
            string $message,
            string $file = '',
            int $line = 0
        ) use ($messageCollector): bool {
            // Critical errors → throw as exception → Whoops handles it
            if ($severity & self::CRITICAL_ERRORS) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            // Respect error_reporting() — if suppressed, skip
            if (!(error_reporting() & $severity)) {
                return false;
            }

            // All other errors → DebugBar MessagesCollector
            $level = $this->severityLabel($severity);
            $messageCollector->addMessage(
                "[$level] $message in $file:$line",
                $level === 'notice' ? 'notice' : 'warning',
                ['file' => $file, 'line' => $line]
            );

            // Return false to also trigger PHP's internal handler
            return false;
        });
    }

    /**
     * Convert a PHP error severity constant to a human-readable label.
     *
     * @param int $severity The PHP error level constant.
     * @return string The label for the DebugBar message.
     * @since 1.0.0
     */
    protected function severityLabel(int $severity): string
    {
        return match ($severity) {
            E_WARNING => 'warning',
            E_NOTICE => 'notice',
            E_USER_WARNING => 'warning',
            E_USER_NOTICE => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'info',
            default => 'warning',
        };
    }
}
