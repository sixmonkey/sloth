<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DebugBar;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DebugBarException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Support\Str;
use Sloth\Core\ServiceProvider;
use Sloth\Debug\Collectors\AcfCollector;
use Sloth\Debug\Collectors\MyDataCollector;
use Sloth\Debug\Collectors\QueryCollector;
use Sloth\Debug\Collectors\SlothCollector;

class DebugServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            ExceptionHandlerContract::class,
            ExceptionHandler::class
        );

        if (class_exists(DebugBar::class)) {
            ob_start([$this, 'appendBar']);
            $this->app->singleton(DebugBar::class, function () {
                return new DebugBar();
            });
        }
    }

    /**
     * @throws DebugBarException
     * @throws BindingResolutionException
     */
    private function configureDebugBar(): void
    {
        $debugbar = $this->app->make(DebugBar::class);
        $debugbar->getJavascriptRenderer()->setBaseUrl('https://php-debugbar.com/assets/');

        $debugbar->addCollector(new MessagesCollector());
        $debugbar->addCollector(new SlothCollector($this->app));
        $debugbar->addCollector(new QueryCollector());
        $debugbar->addCollector(new AcfCollector());

        $messages = $debugbar['messages'];
        $messages->addMessage("hello world!");

        $this->app->instance('debugbar', $debugbar);
    }

    /**
     * @throws DebugBarException
     * @throws BindingResolutionException
     */
    private function renderBar(): string
    {
        $this->configureDebugBar();
        try {
            $debugbar = $this->app->make('debugbar');
            $renderer = $debugbar->getJavascriptRenderer();
            return $renderer->renderHead() . "\n" . $renderer->render();
        } catch (\Throwable) {
        }
    }

    private function appendBar($output): string
    {
        if (Str::contains($output, '</head>')) {
            return Str::replace('</head>', $this->renderBar() . '</head>', $output);
        }
        return $output . $this->renderBar();
    }
}
