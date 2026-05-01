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
 * @see \Sloth\Debug\ExceptionHandler
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
     * Acts as the output buffer callback. Decides whether to inject
     * the DebugBar based on the display configuration and response type.
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

        $messages = collect($debugBar->getMessagesCollector()->getMessages())
            ->map(function ($message) {
                return [
                    $message['xdebug_link']['filename'] . ':' . $message['xdebug_link']['line'] => $message['message'],
                ];
            });

        if (!headers_sent()) {
            header('X-SLOTH_DEBUG: ' . json_encode($messages));
        }

        if (($json = json_decode($output, true)) && config('debugger.json.prepend', true)) {
            $output = json_encode([
                config('debugger.json.key', '__SLOTH_DEBUG') => $messages->toArray(),
                ...$json
            ]);
        }

        return Str::replace(
            '</head>',
            $debugBar->render() . '</head>',
            $output
        );
    }
}
