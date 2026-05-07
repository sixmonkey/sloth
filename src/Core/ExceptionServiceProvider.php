<?php

declare(strict_types=1);
namespace Sloth\Core;

use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Sloth\Exceptions\ExceptionHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * Exception Handler Service Provider.
 *
 * Registers the global exception handler for the Sloth framework.
 * This provider is always active (dev and prod) — exception handling
 * is a core infrastructure concern, not a debug-only feature.
 *
 * In development: the handler renders Whoops error pages with
 * interactive stack traces and editor integration.
 *
 * In production: the handler logs exceptions and renders custom
 * Twig error templates (e.g. Error/500.twig, Error/404.twig).
 *
 * Theme developers can override the handler by registering their own
 * implementation in a ServiceProvider:
 *
 *     $this->app->singleton(
 *         ExceptionHandlerContract::class,
 *         \Theme\Exceptions\Handler::class
 *     );
 *
 * @since 1.0.0
 * @see ExceptionHandler
 */
class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register the exception handler and PHP native handlers.
     *
     * Binds the ExceptionHandlerContract to the Sloth ExceptionHandler
     * implementation as a singleton, and registers set_exception_handler()
     * and set_error_handler() so that uncaught exceptions and PHP errors
     * are routed through our handler.
     *
     * Native handler registration is skipped during unit tests to avoid
     * polluting the global error/exception state between tests.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(
            ExceptionHandlerContract::class,
            ExceptionHandler::class,
        );

        if (!defined('WP_TESTS_PHASE')) {
            $this->registerExceptionHandler();
            $this->registerErrorHandler();
        }
    }

    /**
     * Register PHP's native exception handler.
     *
     * Routes uncaught exceptions through the ExceptionHandlerContract.
     * In CLI context (PHP_SAPI === 'cli'), renderForConsole() is used
     * so exceptions are formatted for terminal output. In web context,
     * render() is used which routes to Whoops in development or a Twig
     * error page in production.
     *
     * @since 1.0.0
     */
    protected function registerExceptionHandler(): void
    {
        $app = $this->app;

        set_exception_handler(function (Throwable $e) use ($app): void {
            $handler = $app->make(ExceptionHandlerContract::class);

            if (PHP_SAPI === 'cli') {
                $handler->renderForConsole(new ConsoleOutput(), $e);

                return;
            }

            $handler->render(null, $e);
        });
    }

    /**
     * Register PHP's native error handler.
     *
     * Converts PHP errors (warnings, notices, deprecated, type errors)
     * to ErrorException so they are routed through the exception handler
     * and rendered by Whoops in development or logged in production.
     *
     * In web context this handler is later replaced by
     * MessageCollectorProvider::registerErrorHandler() which routes
     * non-critical errors to the DebugBar instead.
     *
     * The @ operator is respected — errors suppressed via error_reporting()
     * are silently skipped. All other errors are thrown as ErrorException
     * and caught by the registered exception handler.
     *
     * @since 1.0.0
     */
    protected function registerErrorHandler(): void
    {
        set_error_handler(function (
            int $severity,
            string $message,
            string $file = '',
            int $line = 0,
        ): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });
    }
}
