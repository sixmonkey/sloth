<?php

declare(strict_types=1);

namespace Sloth\Debug;

use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Sloth\Facades\View;
use Tracy\Debugger;
use Tracy\ILogger;
use Throwable;

/**
 * Sloth Exception Handler.
 *
 * Handles all uncaught exceptions and PHP errors.
 * In development: renders Tracy BlueScreen.
 * In production: logs the exception and renders a Twig error page if available.
 *
 * Theme developers can override this by registering their own handler
 * in a ServiceProvider:
 *
 *     $this->app->singleton(
 *         \Illuminate\Contracts\Debug\ExceptionHandler::class,
 *         \Theme\Exceptions\Handler::class
 *     );
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class ExceptionHandler implements ExceptionHandlerContract
{
    /**
     * Scripts that should not trigger debug output.
     *
     * These endpoints return structured data (JSON, XML) — rendering
     * a BlueScreen or HTML error page would corrupt the response.
     *
     * @since 1.0.0
     * @var array<string>
     */
    protected array $dontDebug = [
        'admin-ajax.php',
        'async-upload.php',
    ];

    /**
     * Report (log) an exception.
     *
     * In development: Tracy logs to the log directory.
     * In production: Tracy logs silently without displaying anything.
     *
     * @since 1.0.0
     *
     * @param Throwable $e The exception to report.
     */
    public function report(Throwable $e): void
    {
        if (Debugger::$logDirectory !== null) {
            Debugger::log($e, ILogger::EXCEPTION);
        }
    }

    /**
     * Determine if the exception should be reported.
     *
     * @since 1.0.0
     *
     * @param Throwable $e The exception to check.
     * @return bool True if the exception should be reported.
     */
    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    /**
     * Render an exception as an HTTP response.
     *
     * In development:
     * - AJAX requests → JSON response via Whoops JsonResponseHandler
     * - Browser requests → pretty error page via Whoops PrettyPageHandler
     *
     * In production:
     * - Renders a Twig error template (Error/500.twig, Error/404.twig)
     * - Falls back to a plain error message if no template found
     *
     * Tracy is used only for logging — never for rendering in this handler.
     *
     * @since 1.0.0
     *
     * @param mixed     $request The current HTTP request (unused — WP handles routing).
     * @param Throwable $e       The exception to render.
     */
    public function render($request, Throwable $e): void
    {
        $this->report($e);

        if (app()->isLocal()) {
            $this->renderWithWhoops($e);
            return;
        }

        $this->renderErrorPage($e);
    }

    /**
     * Render an exception using Whoops.
     *
     * Automatically detects AJAX requests and uses the appropriate handler:
     * - AJAX → JsonResponseHandler (errors visible in browser DevTools)
     * - Browser → PrettyPageHandler (full interactive error page)
     *
     * Requires filp/whoops — installed automatically with illuminate/foundation,
     * or via: composer require filp/whoops
     *
     * @since 1.0.0
     *
     * @param Throwable $e The exception to render.
     */
    protected function renderWithWhoops(Throwable $e): void
    {
        $whoops = new \Whoops\Run();

        if ($this->isAjaxRequest()) {
            $handler = new \Whoops\Handler\JsonResponseHandler();
            $handler->setJsonApi(true);
            $handler->addTraceToOutput(true);
        } else {
            $handler = new \Whoops\Handler\PrettyPageHandler();
            $handler->setPageTitle('Sloth — Whoops!');

            if ($editor = env('SLOTH_DEBUGGER_EDITOR')) {
                $handler->setEditor($editor);
            }
        }

        $whoops->pushHandler($handler);
        $whoops->handleException($e);
    }

    /**
     * Render an exception for the console.
     *
     * Not used in WordPress context but required by the interface.
     *
     * @since 1.0.0
     *
     * @param mixed     $output Console output (unused).
     * @param Throwable $e      The exception to render.
     */
    public function renderForConsole($output, Throwable $e): void
    {
        echo $e->getMessage() . PHP_EOL;
        echo $e->getTraceAsString() . PHP_EOL;
    }

    /**
     * Render a Twig error page for the given exception.
     *
     * Looks for View/Error/{statusCode}.twig in the theme,
     * falling back to View/Error/500.twig, then to a plain message.
     *
     * @since 1.0.0
     *
     * @param Throwable $e The exception to render.
     */
    protected function renderErrorPage(Throwable $e): void
    {
        $status = $this->getStatusCode($e);

        http_response_code($status);

        // Try theme error templates: Error/404.twig, Error/500.twig etc.
        $templates = [
            'Error.' . $status,
            'Error.500',
        ];

        foreach ($templates as $template) {
            try {
                echo View::make($template)->with([
                    'exception' => app()->isLocal() ? $e : null,
                    'status'    => $status,
                    'message'   => app()->isLocal() ? $e->getMessage() : 'An error occurred.',
                ])->render();
                return;
            } catch (Throwable) {
                // Template not found — try next
            }
        }

        // Final fallback — plain text
        echo sprintf(
            '<h1>%d — An error occurred.</h1>',
            $status
        );
    }

    /**
     * Determine the HTTP status code for the given exception.
     *
     * @since 1.0.0
     *
     * @param Throwable $e The exception.
     * @return int HTTP status code.
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Check if the current request is an AJAX request.
     *
     * Tracy BlueScreen should not be rendered for AJAX responses
     * as it would corrupt the JSON/XML response.
     *
     * @since 1.0.0
     *
     * @return bool True if this is an AJAX or background request.
     */
    protected function isAjaxRequest(): bool
    {
        $script = basename($_SERVER['PHP_SELF'] ?? '');

        return in_array($script, $this->dontDebug, true)
            || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
                && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
