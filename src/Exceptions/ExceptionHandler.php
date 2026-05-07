<?php

declare(strict_types=1);
namespace Sloth\Exceptions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Sloth\Facades\View;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Sloth Exception Handler.
 *
 * Handles all uncaught exceptions and PHP errors.
 * In development: renders Whoops error page.
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
 * @see \Sloth\Core\ExceptionServiceProvider
 */
class ExceptionHandler implements ExceptionHandlerContract
{
    /**
     * Report (log) an exception.
     *
     * Logs via Illuminate LogManager to the configured log channel.
     *
     * @param Throwable $e the exception to report
     *
     * @since 1.0.0
     */
    public function report(Throwable $e): void
    {
        try {
            $log = app('log');
            $log->error($e->getMessage(), [
                'exception' => $e,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ]);
        } catch (Throwable) {
            // Logging failed - ignore
        }
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param  Throwable $e the exception to check
     * @return bool      true if the exception should be reported
     *
     * @since 1.0.0
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
     * @param mixed     $request the current HTTP request (unused — WP handles routing)
     * @param Throwable $e       the exception to render
     *
     * @throws BindingResolutionException
     *
     * @since 1.0.0
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
     * For browser requests, also injects DebugBar collector data
     * (queries, messages, sloth info) as additional tables in the
     * Whoops error screen.
     *
     * @param Throwable $e the exception to render
     *
     * @since 1.0.0
     */
    protected function renderWithWhoops(Throwable $e): void
    {
        $whoops = new \Whoops\Run();

        $context = app(\Sloth\Http\RequestContext::class);

        if ($context->isAjax() || $context->isXmlRpc() || $context->isRest()) {
            $handler = new \Whoops\Handler\JsonResponseHandler();
            $handler->setJsonApi(true);
            $handler->addTraceToOutput(true);
        } else {
            $handler = new \Whoops\Handler\PrettyPageHandler();
            $handler->setPageTitle('Sloth — Whoops!');

            $this->injectDebugBarData($handler);

            if ($editor = env('SLOTH_DEBUGGER_EDITOR')) {
                $localPath = env('SLOTH_DEBUGGER_LOCAL_PATH');
                $remotePath = env('SLOTH_DEBUGGER_REMOTE_PATH');

                if ($localPath && $remotePath) {
                    $handler->setEditor(function ($file, $line) use ($editor, $localPath, $remotePath): string {
                        $file = str_replace($remotePath, $localPath, $file);
                        $editors = [
                            'phpstorm' => "phpstorm://open?file=$file&line=$line",
                            'vscode'   => "vscode://file/$file:$line",
                            'cursor'   => "cursor://file/$file:$line",
                        ];

                        return $editors[$editor] ?? "phpstorm://open?file=$file&line=$line";
                    });
                } else {
                    $handler->setEditor($editor);
                }
            }
        }

        $whoops->pushHandler($handler);
        $whoops->handleException($e);
    }

    /**
     * Render an exception for the console.
     *
     * Uses Symfony Console's Application::renderThrowable() to produce
     * properly formatted error output with exception class, message,
     * file/line, and a syntax-highlighted stack trace.
     *
     * @param mixed     $output console output (auto-detected if not provided)
     * @param Throwable $e      the exception to render
     *
     * @since 1.0.0
     */
    public function renderForConsole($output, Throwable $e): void
    {
        if (!$output instanceof OutputInterface) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
        }

        new \Symfony\Component\Console\Application()->renderThrowable($e, $output);
    }

    /**
     * Render a Twig error page for the given exception.
     *
     * Looks for View/Error/{statusCode}.twig in the theme,
     * falling back to View/Error/500.twig, then to a plain message.
     *
     * @param Throwable $e the exception to render
     *
     * @since 1.0.0
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
            $status,
        );
    }

    /**
     * Determine the HTTP status code for the given exception.
     *
     * @param  Throwable $e the exception
     * @return int       HTTP status code
     *
     * @since 1.0.0
     */
    protected function getStatusCode(Throwable $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        return 500;
    }

    /**
     * Inject DebugBar collector data into the Whoops PrettyPageHandler.
     *
     * Adds DebugBar collector data as additional data tables in the
     * Whoops error screen so developers can see queries, messages,
     * and framework info without the DebugBar toolbar.
     *
     * Core collectors (messages) are always available. Custom collectors
     * (queries, sloth, acf, wordpress) are only present after boot().
     * Bail-outs are silent so that exception rendering never fails
     * due to missing debug data.
     *
     * @param \Whoops\Handler\PrettyPageHandler $handler the Whoops handler
     *
     * @since 1.0.0
     */
    protected function injectDebugBarData(\Whoops\Handler\PrettyPageHandler $handler): void
    {
        if (!class_exists(\DebugBar\DebugBar::class)) {
            return;
        }

        if (!app()->bound('debugbar')) {
            return;
        }

        $debugBar = app('debugbar');

        if (!($debugBar instanceof \Sloth\Debug\SlothDebugBar)) {
            return;
        }

        // Messages are always available (created in constructor)
        $messages = $debugBar->getMessagesCollector()->collect();
        $messageCount = isset($messages['messages']) ? count($messages['messages']) : 0;

        if ($messageCount > 0) {
            $handler->addDataTable(
                'Messages (' . $messageCount . ')',
                array_reduce($messages['messages'] ?? [], function (array $carry, array $msg): array {
                    $key = $msg['label'] ?? 'info';
                    $value = $msg['message'] ?? '';
                    $carry[$key . ' — ' . $value] = '';

                    return $carry;
                }, []),
            );
        }

        // Custom collectors are only available after boot()
        if (!$debugBar->isBooted()) {
            return;
        }

        if ($debugBar->hasCollector('queries')) {
            $queries = $debugBar->getCollector('queries')->collect();
            $count = $queries['count'] ?? 0;
            $totalTime = $queries['total_time'] ?? 0;
            $handler->addDataTable(
                'Database Queries (' . $count . ' total)',
                [
                    'Count'        => $count,
                    'Total Time'   => round($totalTime, 2) . ' ms',
                    'Slow Queries' => $queries['slow'] ?? 0,
                ],
            );
        }

        if ($debugBar->hasCollector('sloth')) {
            $sloth = $debugBar->getCollector('sloth')->collect();
            $handler->addDataTable('Sloth Framework', $sloth);
        }

        if ($debugBar->hasCollector('acf')) {
            $acf = $debugBar->getCollector('acf')->collect();
            $handler->addDataTable('ACF Field Groups', $acf);
        }

        if ($debugBar->hasCollector('wordpress')) {
            $wp = $debugBar->getCollector('wordpress')->collect();
            $handler->addDataTable('WordPress', $wp);
        }
    }
}
