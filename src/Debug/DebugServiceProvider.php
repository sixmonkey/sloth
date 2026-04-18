<?php

declare(strict_types=1);

namespace Sloth\Debug;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Sloth\Core\ServiceProvider;
use Sloth\Debug\Panels\SlothBarPanel;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Service provider for Sloth debugging and error handling.
 *
 * Responsibilities:
 * - Registers the ExceptionHandler in the container (overridable by themes)
 * - Configures Tracy Debugger for logging and the debug Bar only
 * - Whoops handles error rendering in development (HTML + JSON/AJAX)
 * - Registers set_exception_handler() and set_error_handler()
 * - Suppresses WordPress and plugin deprecated notices
 *
 * ## Error rendering strategy
 *
 * Development:
 * - Tracy Bar is visible with SlothBarPanel
 * - Whoops PrettyPageHandler renders browser errors
 * - Whoops JsonResponseHandler renders AJAX errors (visible in DevTools)
 * - Tracy logs everything to the log directory
 *
 * Production:
 * - No Tracy Bar, no Whoops
 * - Tracy logs silently
 * - ExceptionHandler renders Twig error templates (Error/500.twig etc.)
 *
 * ## Overriding the Exception Handler
 *
 * Theme developers can replace the default handler by registering
 * their own in a ServiceProvider that runs after this one:
 *
 *     $this->app->singleton(
 *         \Illuminate\Contracts\Debug\ExceptionHandler::class,
 *         \Theme\Exceptions\Handler::class
 *     );
 *
 * Since theme providers are registered after framework providers,
 * the container's last-binding-wins behaviour ensures the theme
 * handler takes precedence automatically.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\ExceptionHandler
 * @see \Sloth\Debug\SlothBarPanel
 */
class DebugServiceProvider extends ServiceProvider
{
    /**
     * Register the exception handler in the container.
     *
     * Binds ExceptionHandlerContract to the default Sloth handler.
     * Theme developers can override this binding in their own provider.
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

    /**
     * Boot Tracy and register PHP error/exception handlers.
     *
     * Tracy is configured based on the current environment:
     * - Development (app()->isLocal()): BlueScreen + Bar enabled
     * - Production: silent logging only, Bar disabled
     *
     * The Tracy Bar with the SlothBarPanel is always added in development,
     * giving developers visibility into the current template and environment.
     *
     * PHP's set_exception_handler() and set_error_handler() delegate
     * to the container-bound ExceptionHandlerContract so that theme
     * overrides are respected automatically.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $logPath = $this->resolveLogPath();

        $this->configureTracy($logPath);
        $this->registerExceptionHandler();
        $this->registerErrorHandler();
    }

    /**
     * Resolve the log directory path.
     *
     * Uses app()->path('logs') if available, otherwise falls back
     * to a logs/ directory next to the project root.
     *
     * @return string Absolute path to the log directory.
     * @since 1.0.0
     *
     */
    private function resolveLogPath(): string
    {
        try {
            $path = app('path.logs');
        } catch (\Throwable) {
            $path = dirname(__DIR__, 5) . '/logs';
        }

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Configure Tracy Debugger for logging and the debug Bar.
     *
     * Tracy is used only for:
     * - Logging exceptions and errors to the log directory
     * - Rendering the debug Bar with SlothBarPanel in development
     *
     * Error page rendering is handled by Whoops (development) and
     * the Twig ExceptionHandler (production). Tracy's BlueScreen is
     * intentionally not used — Whoops provides a better experience.
     *
     * The SLOTH_DEBUGGER_EDITOR environment variable configures the
     * editor link format shown in Whoops and Tracy, e.g.:
     *   SLOTH_DEBUGGER_EDITOR=phpstorm://open?file=%file&line=%line
     *
     * @param string $logPath Absolute path to the log directory.
     * @since 1.0.0
     *
     */
    private function configureTracy(string $logPath): void
    {
        Debugger::$showLocation = true;
        Debugger::$logDirectory = $logPath;

        if ($editor = env('SLOTH_DEBUGGER_EDITOR')) {
            Debugger::$editor = $editor;
        }

        if ($this->app->isLocal()) {
            Debugger::enable(Debugger::Development, $logPath);
            Debugger::getBar()->addPanel(new SlothBarPanel());
        } else {
            Debugger::enable(Debugger::Production, $logPath);
        }
    }

    /**
     * Register the PHP exception handler.
     *
     * Delegates to the container-bound ExceptionHandlerContract
     * so that theme overrides are respected.
     *
     * @since 1.0.0
     */
    private function registerExceptionHandler(): void
    {
        set_exception_handler(function (\Throwable $e): void {
            $this->app->make(ExceptionHandlerContract::class)->render(null, $e);
        });
    }

    /**
     * Register the PHP error handler.
     *
     * Suppresses deprecated notices originating from WordPress core
     * and installed plugins to keep the debug output clean.
     * All other errors are logged via Tracy.
     *
     * Suppression can be configured via:
     *   config('errors.suppress_wp_deprecated', true)
     *   config('errors.suppress_plugin_deprecated', true)
     *
     * @since 1.0.0
     */
    private function registerErrorHandler(): void
    {
        set_error_handler(function (
            int $errno,
            string $errstr,
            string $errfile,
            int $errline
        ): bool {
            // Log everything via Tracy
            if (Debugger::$logDirectory !== null) {
                Debugger::log($errstr, ILogger::WARNING);
            }

            // Suppress WP core deprecated notices
            if (
                config('errors.suppress_wp_deprecated', true)
                && defined('ABSPATH')
                && str_contains($errfile, ABSPATH)
            ) {
                return true;
            }

            // Suppress plugin deprecated notices
            if (
                config('errors.suppress_plugin_deprecated', true)
                && defined('WP_PLUGIN_DIR')
                && str_contains($errfile, WP_PLUGIN_DIR)
            ) {
                return true;
            }

            // Suppress errors during REST requests
            if (function_exists('wp_is_serving_rest_request') && \wp_is_serving_rest_request()) {
                return true;
            }

            return false;
        });
    }
}
