<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use Sloth\Core\Application;

class SlothDebugBar extends DebugBar
{
    protected TimeDataCollector $timeCollector;
    protected MessagesCollector $messagesCollector;
    protected ExceptionsCollector $exceptionsCollector;

    protected bool $booted = false;

    public function __construct(protected Application $app)
    {
        $start = defined('SLOTH_START') ? SLOTH_START : microtime(true);

        $this->timeCollector = new TimeDataCollector($start);
        $this->messagesCollector = new MessagesCollector();
        $this->exceptionsCollector = new ExceptionsCollector();
    }

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

    public function getTimeCollector(): TimeDataCollector
    {
        return $this->timeCollector;
    }

    public function getMessagesCollector(): MessagesCollector
    {
        return $this->messagesCollector;
    }

    public function getExceptionsCollector(): ExceptionsCollector
    {
        return $this->exceptionsCollector;
    }

    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Magic calls for adding messages
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
     * Adds a message to the MessagesCollector
     *
     * A message can be anything from an object to a string
     */
    public function addMessage(mixed $message, string $label = 'info', array $context = []): void
    {
        $this->messagesCollector->addMessage($message, $label, $context);
    }

    public function render(): string
    {
        $renderer = $this->getJavascriptRenderer();
        $renderer->setBaseUrl('https://php-debugbar.com/assets/');
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
