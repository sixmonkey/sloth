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
use Sloth\Debug\Collectors\QueryCollector;
use Sloth\Debug\Collectors\SlothCollector;

/**
 * Sloth Debug Service Provider.
 *
 * Registers PHP DebugBar for development environments and custom
 * exception handling for the Sloth WordPress theme.
 *
 * @since 1.0.0
 * @see \Sloth\Debug\ExceptionHandler
 */
class DebugServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * Registers custom exception handling and initializes PHP DebugBar
     * when available.
     *
     * @since 1.0.0
     */
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
     * Configure the PHP DebugBar.
     *
     * Sets up the debug bar with custom collectors for Sloth,
     * WordPress, ACF, and database queries.
     *
     * @return void
     *  @throws DebugBarException
     * @throws BindingResolutionException
     * @since 1.0.0
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
     * Render the PHP DebugBar HTML.
     *
     * Renders the debug bar JavaScript and CSS for injection
     * into the page HTML.
     *
     * @return string The rendered debug bar HTML.
     *  @throws DebugBarException
     * @throws BindingResolutionException
     * @since 1.0.0
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

    /**
     * Append the debug bar to page output.
     *
     * Injects the debug bar HTML into the page by replacing
     * the closing </head> tag or appending to the body.
     *
     * @param string $output The page HTML output.
     * @return string The output with debug bar injected.
     * @throws BindingResolutionException
     * @throws DebugBarException
     * @since 1.0.0
     */
    private function appendBar($output): string
    {
        if (Str::contains($output, '</head>')) {
            return Str::replace('</head>', $this->renderBar() . '</head>', $output);
        }
        return $output . $this->renderBar();
    }
}
