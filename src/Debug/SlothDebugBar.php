<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Sloth\Core\Application;

/**
 * Extended PHP DebugBar for the Sloth framework.
 *
 * Wraps the base DebugBar with core collectors (time, messages,
 * exceptions) and provides a boot() method that loads custom
 * collector providers from the debugger configuration.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\DebugServiceProvider
 */
class SlothDebugBar extends DebugBar
{
    /**
     * Time data collector for measuring request duration.
     *
     * @since 1.0.0
     */
    protected TimeDataCollector $timeCollector;

    /**
     * Messages data collector for log messages and dump output.
     *
     * @since 1.0.0
     */
    protected MessagesCollector $messagesCollector;

    /**
     * Exceptions data collector for capturing thrown exceptions.
     *
     * @since 1.0.0
     */
    protected ExceptionsCollector $exceptionsCollector;

    /**
     * Whether the DebugBar has been fully booted.
     *
     * Set to true after boot() has loaded all collector providers.
     * Use isBooted() to check whether custom collectors are available.
     *
     * @since 1.0.0
     */
    protected bool $booted = false;

    /**
     * Create a new SlothDebugBar instance.
     *
     * Initializes the three core collectors with the application
     * start time (from SLOTH_START constant or microtime).
     *
     * @param Application $app The application container.
     * @since 1.0.0
     */
    public function __construct(protected Application $app)
    {
        $start = defined('SLOTH_START') ? SLOTH_START : microtime(true);

        $this->timeCollector = new TimeDataCollector($start);
        $this->messagesCollector = new MessagesCollector();
        $this->exceptionsCollector = new ExceptionsCollector();
    }

    /**
     * Boot the DebugBar by loading all registered collector providers.
     *
     * Each provider from `debugger.bar.collector_providers` is
     * instantiated with this DebugBar instance and booted, which
     * adds its collectors (queries, sloth, acf, wordpress, etc.).
     *
     * Idempotent — calling boot() multiple times is safe.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        collect(config('debugger.bar.collector_providers', []))
            ->each(function ($collectorProvider) {
                (new $collectorProvider($this))->boot();
            });

        $this->booted = true;
    }

    /**
     * Get the time data collector.
     *
     * @return TimeDataCollector
     * @since 1.0.0
     */
    public function getTimeCollector(): TimeDataCollector
    {
        return $this->timeCollector;
    }

    /**
     * Get the messages data collector.
     *
     * @return MessagesCollector
     * @since 1.0.0
     */
    public function getMessagesCollector(): MessagesCollector
    {
        return $this->messagesCollector;
    }

    /**
     * Get the exceptions data collector.
     *
     * @return ExceptionsCollector
     * @since 1.0.0
     */
    public function getExceptionsCollector(): ExceptionsCollector
    {
        return $this->exceptionsCollector;
    }

    /**
     * Check whether the DebugBar has been fully booted.
     *
     * Returns true after all collector providers have been loaded
     * via boot(). Use this to determine whether custom collectors
     * (queries, sloth, acf, wordpress) are available.
     *
     * @return bool
     * @since 1.0.0
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Magic calls for adding messages to the MessagesCollector.
     *
     * Supports all PSR-3 log levels: emergency, alert, critical,
     * error, warning, notice, info, debug, log.
     *
     *     $debugBar->error('Something went wrong');
     *     $debugBar->info('Processing request');
     *
     * @param string $method The PSR-3 log level.
     * @param array $args Messages to add.
     * @since 1.0.0
     */
    public function __call(string $method, array $args): void
    {
        $messageLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug', 'log'];
        if (in_array($method, $messageLevels, true)) {
            foreach ($args as $arg) {
                $this->addMessage($arg, $method);
            }
        }
    }

    /**
     * Add a message to the MessagesCollector.
     *
     * A message can be anything from a scalar value to a complex object.
     *
     * @param mixed $message The message content.
     * @param string $label The message label/level (default: 'info').
     * @param array $context Optional context data.
     * @since 1.0.0
     */
    public function addMessage(mixed $message, string $label = 'info', array $context = []): void
    {
        $this->messagesCollector->addMessage($message, $label, $context);
    }

    /**
     * Render the DebugBar toolbar HTML for injection into the page.
     *
     * Configures the JavaScript renderer with the php-debugbar CDN
     * base URL and injects custom Sloth CSS for icons and styling.
     *
     * @return string The DebugBar HTML (head + toolbar scripts).
     * @throws BindingResolutionException
     * @throws FileNotFoundException
     * @since 1.0.0
     */
    public function render(): string
    {
        $renderer = $this->getJavascriptRenderer();

        $renderer->addInlineAssets(
            $renderer->dumpCssAssets(echo: false),
            $renderer->dumpJsAssets(echo: false),
            $renderer->dumpHeadAssets(echo: false)
        );
        $renderer->addInlineAssets(
            app('files')->get(__DIR__ . '/resources/sloth-debugbar-icons.css'),
            '',
            ''
        );
        $renderer->addInlineAssets(
            app('files')->get(__DIR__ . '/resources/sloth-debugbar.css'),
            '',
            ''
        );
        return $renderer->renderHead() . "\n" . $renderer->render();
    }
}
