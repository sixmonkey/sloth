<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DebugBar;
use Illuminate\Support\Str;
use Sloth\Core\ServiceProvider;

/**
 * Sloth Debug Service Provider.
 *
 * Registers PHP DebugBar for development environments and custom
 * exception handling for the Sloth WordPress theme.
 *
 * The DebugBar is only registered when the `php-debugbar/php-debugbar`
 * package is installed as a dev-dependency. In production (where the
 * package is absent), this provider does nothing.
 *
 * @since 1.0.0
 * @see \Sloth\Exceptions\ExceptionHandler
 */
class DebugServiceProvider extends ServiceProvider
{
    /**
     * Whether the DebugBar has been successfully registered.
     *
     * @since 1.0.0
     */
    protected bool $enabled = false;

    /**
     * Register the service provider.
     *
     * Bail-out early if the DebugBar class is not available
     * (e.g. production where the dev-dependency is not installed).
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        /**
         * Early bail-out: The DebugBar package must be installed.
         *
         * In production environments where `php-debugbar/php-debugbar`
         * is not required, this provider will silently do nothing.
         *
         * @see https://getcomposer.org/doc/04-schema.md#require-dev
         */
        if (! class_exists(DebugBar::class)) {
            return;
        }

        $this->app->singleton(SlothDebugBar::class);
        $this->app->alias(SlothDebugBar::class, 'debugbar');
        $this->app->alias(SlothDebugBar::class, DebugBar::class);

        /**
         * Hook into output buffering to inject the DebugBar.
         *
         * The actual rendering decision (display flag, response type)
         * is handled inside renderBar().
         */
        ob_start([$this, 'renderBar']);

        $this->enabled = true;
    }

    /**
     * Boot the DebugBar collectors.
     *
     * Resolves the SlothDebugBar instance during boot to ensure it
     * is loaded inside the Octane sandbox.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->mergeConfigFrom(
            __DIR__ . '/config/debugger.php',
            'debugger'
        );

        try {
            $debugBar = $this->app->make(SlothDebugBar::class);
            $debugBar->boot();
        } catch (\Throwable $e) {
            $this->handleBootError($e);
        }
    }

    /**
     * Handle a boot error for the DebugBar.
     *
     * Logs the error to the application log instead of silently
     * ignoring it, so that developers are aware of the failure.
     *
     * @param \Throwable $e The exception that occurred during boot.
     * @since 1.0.0
     */
    protected function handleBootError(\Throwable $e): void
    {
        try {
            app('log')->error('Sloth DebugBar boot failed', [
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        } catch (\Throwable) {
            // Logging failed — nothing more we can do
        }
    }

    /**
     * Render the DebugBar or pass through the original output.
     *
     * Acts as the output buffer callback. Dispatches to the appropriate
     * handler based on the response type (JSON vs HTML).
     *
     * @param string $output The buffered page output.
     * @return string The modified or unmodified output.
     * @since 1.0.0
     */
    public function renderBar($output): string
    {
        if (! $this->enabled) {
            return $output;
        }

        /**
         * Respect the display configuration flag.
         *
         * By default, the DebugBar is only displayed in local environments.
         */
        if (! config('debugger.bar.display', true)) {
            return $output;
        }

        $debugBar = $this->app->make(SlothDebugBar::class);

        /**
         * Dispatch based on response type.
         *
         * - JSON responses receive slim debug messages via X-SLOTH_DEBUG header.
         *   Only messages are sent (not the full dataset) to avoid FastCGI header limits.
         * - HTML responses have the DebugBar toolbar injected before </head>.
         */
        if ($this->isJsonResponse($output)) {
            return $this->handleJsonResponse($debugBar, $output);
        }

        return $this->handleHtmlResponse($debugBar, $output);
    }

    /**
     * Determine if the output is a JSON response.
     *
     * Checks WordPress context indicators first (O(1)), then falls back
     * to inspecting the first non-whitespace character of the output.
     *
     * @param string $output The buffered page output.
     * @return bool True if the output appears to be JSON.
     * @since 1.0.0
     */
    protected function isJsonResponse(string $output): bool
    {
        /**
         * WordPress-native AJAX and REST context checks.
         *
         * These are cheap and reliable within the WordPress request lifecycle.
         */
        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        /**
         * Fallback: inspect the first non-whitespace character.
         *
         * Valid JSON must start with '{' (object) or '[' (array).
         * This is O(1) compared to json_decode() which parses the entire string.
         */
        $trimmed = ltrim($output);
        if ($trimmed === '') {
            return false;
        }

        return in_array($trimmed[0], ['{', '['], true);
    }

    /**
     * Handle a JSON response by sending debug messages via a slim HTTP header.
     *
     * Sends only the collected messages (not the full dataset) in a compact
     * X-SLOTH_DEBUG header to avoid exceeding FastCGI header size limits.
     *
     * @param SlothDebugBar $debugBar The DebugBar instance.
     * @param string $output The original JSON output (returned unchanged).
     * @return string The unmodified JSON output.
     * @since 1.0.0
     */
    protected function handleJsonResponse(SlothDebugBar $debugBar, string $output): string
    {
        if (headers_sent()) {
            return $output;
        }

        /**
         * Build a slim message map from the MessagesCollector.
         *
         * Uses xdebug_link when available (IDE integration), falls back
         * to file:line from the message trace, or 'unknown' if neither
         * is present.
         */
        $messages = collect($debugBar->getMessagesCollector()->getMessages())
            ->map(function ($message) {
                $link = $message['xdebug_link'] ?? null;
                if ($link) {
                    $source = $link['filename'] . ':' . $link['line'];
                } elseif (isset($message['trace']) && is_array($message['trace']) && count($message['trace']) > 0) {
                    $frame = $message['trace'][0];
                    $source = ($frame['file'] ?? 'unknown') . ':' . ($frame['line'] ?? '?');
                } else {
                    $source = 'unknown';
                }

                return [
                    $source => $message['message'] ?? '',
                ];
            });

        if ($messages->isNotEmpty()) {
            header('X-SLOTH_DEBUG: ' . json_encode($messages->toArray()));
        }

        /**
         * Optionally prepend debug data into the JSON body.
         *
         * Uses the configured key (default: '__debug') and merges
         * the slim messages as the first property.
         */
        if (config('debugger.json.prepend', true) && ($json = json_decode($output, true))) {
            $key = config('debugger.json.key', '__debug');

            return json_encode([
                $key => $messages->toArray(),
                ...$json,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $output;
    }

    /**
     * Handle an HTML response by injecting the DebugBar toolbar.
     *
     * Renders the DebugBar JavaScript and CSS, then injects it
     * before the closing </head> tag.
     *
     * @param SlothDebugBar $debugBar The DebugBar instance.
     * @param string $output The buffered HTML output.
     * @return string The HTML output with DebugBar injected.
     * @since 1.0.0
     */
    protected function handleHtmlResponse(SlothDebugBar $debugBar, string $output): string
    {
        return Str::replace(
            '</head>',
            $debugBar->render() . '</head>',
            $output
        );
    }
}
