<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DebugBar;
use Illuminate\Support\Str;
use Sloth\Core\ServiceProvider;

class DebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SlothDebugBar::class);
        $this->app->alias(SlothDebugBar::class, 'debugbar');
        $this->app->alias(SlothDebugBar::class, DebugBar::class);

        ob_start([$this, 'renderBar']);
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/debugger.php',
            'debugger'
        );

        // Resolve the SlothDebugBar instance during boot to force it to be loaded in the Octane sandbox
        try {
            $debugBar = $this->app->make(SlothDebugBar::class);
            $debugBar->boot();
        } catch (\Throwable $e) {
            return;
        }
    }

    public function renderBar($output): string
    {
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

        if (($json = json_decode($output, true)) && config('debugger.json.prepend', false)) {
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
