<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Sloth\Exceptions\ExceptionHandler;

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
 * @see \Sloth\Exceptions\ExceptionHandler
 */
class ExceptionServiceProvider extends ServiceProvider
{
    /**
     * Register the exception handler.
     *
     * Binds the ExceptionHandlerContract to the Sloth ExceptionHandler
     * implementation as a singleton.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton(
            ExceptionHandlerContract::class,
            ExceptionHandler::class
        );
    }
}
