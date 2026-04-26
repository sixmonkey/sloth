<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DebugBar;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Log\LogManager;
use Sloth\Core\ServiceProvider;
use Sloth\Debug\Collectors\SlothCollector;
use Sloth\Debug\Collectors\WordPressCollector;
use Sloth\Debug\Collectors\TemplateCollector;
use Sloth\Debug\Collectors\AcfCollector;
use Sloth\Debug\Collectors\QueryCollector;

/**
 * Service provider for Sloth debugging and error handling.
 *
 * Responsibilities:
 * - Boots Illuminate LogManager for logging
 * - Registers the ExceptionHandler in the container (overridable by themes)
 * - Configures php-debugbar for the debug Bar only
 * - Whoops handles error rendering in development (HTML + JSON/AJAX)
 * - Registers set_exception_handler() and set_error_handler()
 * - Suppresses WordPress and plugin deprecated notices
 *
 * ## Error rendering strategy
 *
 * Development:
 * - php-debugbar with SlothCollectors
 * - Whoops PrettyPageHandler renders browser errors
 * - Whoops JsonResponseHandler renders AJAX errors (visible in DevTools)
 * - LogManager logs everything to the log directory
 *
 * Production:
 * - No debug bar, no Whoops
 * - LogManager logs silently
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
 * @see \Sloth\Debug\Collectors
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

        $this->app->singleton(DebugBar::class, function () {
            return new DebugBar();
        });
    }

    /**
     * Boot php-debugbar and register PHP error/exception handlers.
     *
     * php-debugbar is configured based on the current environment:
     * - Development (app()->isLocal()): collectors enabled
     * - Production: debug bar disabled
     *
     * The debug bar with SlothCollectors is always added in development,
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
        $this->configureDebugBar();
        $this->registerExceptionHandler();
        $this->registerErrorHandler();
    }

    /**
     * Configure php-debugbar with Sloth collectors.
     *
     * php-debugbar is used only for:
     * - Rendering the debug bar with SlothCollectors in development
     *
     * Error page rendering is handled by Whoops (development) and
     * the Twig ExceptionHandler (production). Debug bar is
     * intentionally not used for error display.
     *
     * @since 1.0.0
     *
     */
    private function configureDebugBar(): void
    {
        if (!$this->app->isLocal()) {
            return;
        }

        $debugbar = $this->app->make(DebugBar::class);

        $debugbar->addCollector(new SlothCollector($this->app));
        $debugbar->addCollector(new WordPressCollector());
        $debugbar->addCollector(new TemplateCollector());
        $debugbar->addCollector(new AcfCollector());
        $debugbar->addCollector(new QueryCollector());
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
     * All other errors are logged via LogManager.
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
            try {
                $log = $this->app->make('log');
                $log->warning($errstr);
            } catch (\Throwable) {
                // Ignore logging errors
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