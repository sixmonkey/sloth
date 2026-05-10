<?php

/**
 * Pest Test Suite Configuration
 *
 * @since 1.1.0
 */

use Brain\Monkey;
use Sloth\Core\Application;
use Sloth\Console\ConsoleKernel;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * Create a minimal test Application for console tests.
 *
 * @return Application The configured test application.
 * @since 1.0.0
 */
function makeTestApp(): Application
{
    $app = Application::configure();
    $app->instance('config', new \Illuminate\Config\Repository([]));
    $app->instance('files', new \Illuminate\Filesystem\Filesystem());
    $app->instance('events', new \Illuminate\Events\Dispatcher($app));

    $app->instance('path.base', sys_get_temp_dir());
    $app->instance('path.app', sys_get_temp_dir() . '/app');
    $app->instance('path.theme', sys_get_temp_dir() . '/theme');
    $app->instance('path.cache', sys_get_temp_dir() . '/cache');

    $app->instance('uri.home', 'http://example.com');
    $app->instance('uri.theme', 'http://example.com/wp-content/themes/sloth/');
    $app->instance('uri.content', 'http://example.com/wp-content/');
    $app->instance('uri.uploads', 'http://example.com/wp-content/uploads/');

    return $app;
}

/**
 * Create a ConsoleKernel that routes all output to a BufferedOutput.
 *
 * Prevents terminal output during tests by overriding run() to write
 * to an in-memory buffer instead of php://stdout.
 *
 * @param Application|null $app Optional application — creates a test app if not provided.
 * @return ConsoleKernel The silent kernel instance.
 * @since 1.0.0
 */
function makeTestKernel(?Application $app = null): ConsoleKernel
{
    return new class ($app ?? makeTestApp()) extends ConsoleKernel {
        #[\Override]
        protected function run(array $argv, ?OutputInterface $output = null): int
        {
            return parent::run($argv, $output ?? new BufferedOutput());
        }
    };
}

beforeEach(function (): void {
    Monkey\setUp();
    Application::setInstance(makeTestApp());
});

afterEach(function (): void {
    Monkey\tearDown();
});
