<?php

declare(strict_types=1);

namespace Sloth\Debug;

use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DebugBar;
use DebugBar\DebugBarException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
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
    protected $timeCollector;
    protected $messagesCollector;
    protected $exceptionsCollector;
    public function __construct($app)
    {
        $startTime = defined('SLOTH_START') ? (float) SLOTH_START : microtime(true);

        $this->app = $app;
        $this->timeCollector = new TimeDataCollector($startTime);
        $this->messagesCollector = new MessagesCollector();
        $this->exceptionsCollector = new ExceptionsCollector();
    }

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
        $this->publishes([
            __DIR__ . '/../../config/debugger.php' => $this->app->configPath('debugger.php'),
        ], 'config');

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
     * Boots the DebugServiceProvider
     *
     * @throws DebugBarException
     * @throws FileNotFoundException
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/debugger.php',
            'debugger'
        );
        $this->configureDebugBar();
    }

    /**
     * Configure the PHP DebugBar.
     *
     * Sets up the debug bar with custom collectors for Sloth,
     * WordPress, ACF, and database queries.
     *
     * @return void
     * @throws DebugBarException
     * @throws BindingResolutionException|FileNotFoundException
     * @since 1.0.0
     */
    private function configureDebugBar(): void
    {
        $debugBar = $this->app->make(DebugBar::class);
        $debugBar->getJavascriptRenderer()->setBaseUrl('https://php-debugbar.com/assets/');

        collect(config('debugger.bar.collector_providers', []))
            ->each(function ($provider) use (&$debugBar) {
                (new $provider($debugBar))->boot();
            });

        $debugBar->addCollector(new SlothCollector());
        $debugBar->addCollector(new QueryCollector());
        $debugBar->addCollector(new AcfCollector());

        $renderer = $debugBar->getJavascriptRenderer();

        $renderer->addInlineAssets(
            app('files')->get(__DIR__ . '/../../resources/sloth-debugbar.css'),
            '',
            ''
        );

        $this->app->instance('debugbar', $debugBar);
    }

    /**
     * Render the PHP DebugBar HTML.
     *
     * Renders the debug bar JavaScript and CSS for injection
     * into the page HTML.
     *
     * @return string The rendered debug bar HTML.
     * @since 1.0.0
     */
    private function renderBar(): string
    {
        try {
            $debugBar = $this->app->make('debugbar');
            $renderer = $debugBar->getJavascriptRenderer();
            return $renderer->renderHead() . "\n" . $renderer->render();
        } catch (\Throwable) {
            return '';
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
     * @since 1.0.0
     */
    private function appendBar($output): string
    {
        if (Str::contains($output, '</head>')) {
            return Str::replace('</head>', $this->renderBar() . '</head>', $output);
        }
        return $output . $this->renderBar();
    }

    public function __destruct()
    {
        // TODO: Implement __destruct() method.
    }
}
