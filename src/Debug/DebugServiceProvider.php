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
     * @throws DebugBarException
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
     * @throws DebugBarException
     * @throws BindingResolutionException
     * @since 1.0.0
     */
    private function renderBar(): string
    {
        $this->configureDebugBar();
        try {
            $debugbar = $this->app->make('debugbar');
            $renderer = $debugbar->getJavascriptRenderer();
            $renderer->addInlineAssets(
                [
                    ":root {
                    --debugbar-icon-brand-php: url(\"data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyBpZD0ic3ZnMSIgdmVyc2lvbj0iMS4xIiB2aWV3Qm94PSIwIDAgNjQgNjQiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggaWQ9InBhdGgxOCIgZD0ibTMwLjk1MyA0LjA2MjRjLTIuNTg2NyAwLjQ2NDU4LTEuMDc2OSAyLjEwODYgMC4wNDg4NyA2LjQ0NjgtMTcuODI3LTAuMTIyNS0yOS45NjYgMTItMzAuOTQyIDI1LjQyNi0xLjAyMzggMTQuMDc2IDExLjEwNCAyMy4yMDUgMzAuMzE4IDI0LjYwMyAxOS4yMTQgMS4zOTc1IDMyLjUzNi01Ljg4MDIgMzMuNTYtMTkuOTU3IDAuODk5NjEtMTIuMzY5LTguMDY0LTI1LjAzMy0yMy4yODYtMjguODczLTAuMTUyNzctMi45OTgxLTAuNTg3MTUtNy43NzA3LTMuNDAzNy03LjY2NTctMC4zNDM1NiAwLjAxMjg1LTEuMDAxOC0wLjc0OTI2LTEuODE3MSAzLjcwNzItMC45MTQzMS0yLjU1OTEtMy40MjkzLTMuODc1Mi00LjQ3OC0zLjY4Njh6bS0xNC4wNjcgMTUuODY4YzYuOTM2Mi0xLjU4MTQgMTQuNzM5IDEuNzA1OSAxNi4zMDIgMi40MTQzIDIuNzYtMC43OTQzMSAyMy4yNTEtNi4wMjkxIDI1LjE5NyAxMi42NzYtMS4zNTYxLTIuMjg1Ni0zLjg0MDgtNC4zMjc5LTguMTMyOC01LjYyNTgtMS4xMzA5LTAuMzQxOTctMi4xNzA1LTAuNTM5LTMuMTE4OC0wLjYxMzA4LTkuMTY2NS0wLjcxNjE4LTkuNzk4OCAxMC4wNi0xLjg0NDQgMTIuNTM5IDMuNjAwMSAxLjEyMiA2LjgzOTUgMy4zMTI1IDkuMDggNS4wOTI5LTUuMDg5OSA2LjMzNjQtMTQuMTM4IDkuNTU1MS0yMy41NzkgOC45MDE2LTkuMjczMS0wLjY4NjQ4LTE4LjU1Ni00LjgyOTUtMjIuNjctMTEuODkyIDIuNDc4NS0xLjQ5NDcgNi4yNjgtMy40MjI0IDEwLjI4NS00LjA1ODEgOC4yMjk2LTEuMzAyMSA5LjE2MzEtMTIuMDU2LTAuMDEwOTYtMTIuNjc0LTAuOTQ5LTAuMDYzODc1LTIuMDA2MS0wLjAxOTI3Ni0zLjE3NDYgMC4xNTUzOC00LjM2NzMgMC42NTMxNC03LjEwMzMgMi4yNzUyLTguNzgyOSA0LjI5NyAxLjgzNy03LjEyMDcgNS45NTk2LTEwLjE5IDEwLjQ0OS0xMS4yMTN6bS0wLjYwMzc4IDkuNTQ5YzAuMzYyNjEtMC4wODQ1MSAwLjc0Mzk2LTAuMTE2NTIgMS4xMzQ4LTAuMDg4MSAyLjA4NDggMC4xNTE1MiAzLjY1MTkgMS45NjQ2IDMuNTAwMyA0LjA0OTQtMC4xNTE1MiAyLjA4NDktMS45NjQ4IDMuNjUyMS00LjA0OTggMy41MDA0LTIuMDg0OC0wLjE1MTcxLTMuNjUxOC0xLjk2NDktMy41MDAxLTQuMDQ5OCAwLjEyMzI1LTEuNjkzOCAxLjM0MzMtMy4wNDU3IDIuOTE0Ni0zLjQxMTl6bTMwLjI5NSAyLjIwMzRjMC4zNjI2MS0wLjA4NDUxIDAuNzQzOTYtMC4xMTY1MiAxLjEzNDgtMC4wODgxIDIuMDg0OCAwLjE1MTUyIDMuNjUxOCAxLjk2NDMgMy41MDAzIDQuMDQ5LTAuMTUxNTIgMi4wODQ5LTEuOTY0NCAzLjY1MjQtNC4wNDk0IDMuNTAwNi0yLjA4NDgtMC4xNTE3MS0zLjY1MjEtMS45NjQ5LTMuNTAwNC00LjA0OTggMC4xMjMyNS0xLjY5MzggMS4zNDMzLTMuMDQ1NyAyLjkxNDYtMy40MTE5em0tMTYuNzMxIDQuMzE4MWE2LjA0MjcgMy43MTU2IDQuMTU5OCAwIDAtMy45Njk2IDMuMTUyOSA2LjA0MjcgMy43MTU2IDQuMTU5OCAwIDAgNS43NTc0IDQuMTQ0MyA2LjA0MjcgMy43MTU2IDQuMTU5OCAwIDAgNi4yOTYtMy4yNjc0IDYuMDQyNyAzLjcxNTYgNC4xNTk4IDAgMC01Ljc1Ny00LjE0MzkgNi4wNDI3IDMuNzE1NiA0LjE1OTggMCAwLTIuMzI2OCAwLjExNDMzem0tOS43NDc4IDYuMDI3NmEwLjg5MDY1IDAuODkwNjUgMCAwIDAtMC42MDgwOSAwLjc4MDE3Yy0wLjA5NzQ0IDEuMzM5OCAwLjUxMzQxIDIuNjI0OSAxLjU0MjMgMy42ODY4IDEuMDI4OCAxLjA2MTkgMi40ODc1IDEuOTUgNC4yODAzIDIuNjI4MyAzLjU4NTMgMS4zNTY2IDcuOTg4OCAxLjY3NjcgMTEuNzMzIDAuODUzMTYgMS44NzE5LTAuNDExNzggMy40NDM1LTEuMDc5MiA0LjYxNTItMS45ODEgMS4xNzE3LTAuOTAxOCAxLjk2MjEtMi4wODUxIDIuMDU5Ni0zLjQyNDlhMC44OTA2NSAwLjg5MDY1IDAgMCAwLTAuODIzNjQtMC45NTI1MiAwLjg5MDY1IDAuODkwNjUgMCAwIDAtMC45NTM4OCAwLjgyMzI2Yy0wLjA1Mjg0IDAuNzI2NjItMC40NzU4NCAxLjQ1NTItMS4zNjgyIDIuMTQyMS0wLjg5MjM5IDAuNjg2ODYtMi4yMzUgMS4yODUzLTMuOTExOCAxLjY1NDEtMy4zNTM0IDAuNzM3NjItNy41MDg4IDAuNDM1NTUtMTAuNzItMC43Nzk1My0xLjYwNTctMC42MDc1Mi0yLjg0NzctMS4zOTQtMy42MzEzLTIuMjAyOHMtMS4wOTY3LTEuNTkxLTEuMDQzOS0yLjMxNzZhMC44OTA2NSAwLjg5MDY1IDAgMCAwLTAuODI0OTItMC45NTI2IDAuODkwNjUgMC44OTA2NSAwIDAgMC0wLjM0NDUgMC4wNDMxNnoiIGZpbGw9ImN1cnJlbnRDb2xvciIgc3Ryb2tlPSJub25lIiAvPjwvc3ZnPgo=\");
                    }
                    div.phpdebugbar[data-theme=dark] {
                    --debugbar-text: #CDCDCD;
                    --debugbar-header-text: #00FF77;
                    --debugbar-icons: #FF0077;
                    }",
                ],
                '',
                ''
            );
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
