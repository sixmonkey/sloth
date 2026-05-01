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
        return Str::replace(
            '</head>',
            $this->app->make(SlothDebugBar::class)->render() . '</head>',
            $output
        );
    }
}
