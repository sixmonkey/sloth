<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DebugBar;
use Sloth\Core\ServiceProvider;

/**
 * Sloth Debug Service Provider.
 *
 * Registers PHP DebugBar for development environments.
 *
 * The DebugBar is only registered when the `php-debugbar/php-debugbar`
 * package is installed as a dev-dependency. In production (where the
 * package is absent), this provider does nothing.
 *
 * Uses a hybrid approach:
 * - `ob_start()` callback for JSON responses (injects `__debug` key into body)
 * - `register_shutdown_function()` for HTML responses (appends DebugBar after `die()`)
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
        if (!class_exists(DebugBar::class)) {
            return;
        }

        $this->app->singleton(SlothDebugBar::class);
        $this->app->alias(SlothDebugBar::class, 'debugbar');
        $this->app->alias(SlothDebugBar::class, DebugBar::class);

        $this->enabled = true;

        /**
         * Output buffer callback for JSON responses.
         *
         * Injects `__debug` key into JSON body when debugger.json.prepend
         * is enabled. For HTML responses, passes buffer through unchanged
         * — the shutdown function appends the DebugBar toolbar.
         */
        ob_start(function ($output) {
            if (!$this->enabled) {
                return $output;
            }

            if (!config('debugger.json.prepend', true)) {
                return $output;
            }

            try {
                $context = $this->app->make(\Sloth\Http\RequestContext::class);

                if (!$context->isJsonResponse($output)) {
                    return $output;
                }

                $debugBar = $this->app->make(SlothDebugBar::class);
                $messages = [];

                foreach ($debugBar->getMessagesCollector()->getMessages() as $entry) {
                    $link = $entry['xdebug_link'] ?? null;
                    if ($link) {
                        $source = $link['filename'] . ':' . $link['line'];
                    } elseif (isset($entry['trace']) && is_array($entry['trace']) && count($entry['trace']) > 0) {
                        $frame = $entry['trace'][0];
                        $source = ($frame['file'] ?? 'unknown') . ':' . ($frame['line'] ?? '?');
                    } else {
                        $source = 'unknown';
                    }

                    if (!empty($entry['is_string'])) {
                        $value = $entry['message'] ?? '';
                    } elseif (!empty($entry['message_html'])) {
                        $value = strip_tags($entry['message_html']);
                    } elseif (!empty($entry['message_json'])) {
                        $value = $entry['message_json'];
                    } else {
                        $value = '[dump]';
                    }

                    $messages[$source] = $value;
                }

                if ($messages && ($json = json_decode($output, true))) {
                    $key = config('debugger.json.key', '__debug');

                    return json_encode([
                        $key => $messages,
                        ...$json,
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            } catch (\Throwable) {
                // DebugBar not available — pass through unchanged
            }

            return $output;
        });

        /**
         * Register shutdown function to inject DebugBar toolbar for HTML responses.
         *
         * Works correctly with `die()` calls in templates (e.g. TemplateServiceProvider)
         * because it runs after all output is flushed and can safely echo content.
         */
        register_shutdown_function(function () {
            if (!$this->enabled) {
                return;
            }

            if (!config('debugger.bar.display', true)) {
                return;
            }

            try {
                $debugBar = $this->app->make(SlothDebugBar::class);
                $context = $this->app->make(\Sloth\Http\RequestContext::class);

                if (
                    !$context->isJsonResponse() &&
                    !$context->isXmlResponse() &&
                    !$context->isCli()
                ) {
                    echo $debugBar->render();
                }
            } catch (\Throwable) {
                // DebugBar not available — skip silently
            }
        });
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
        if (!$this->enabled) {
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
}
