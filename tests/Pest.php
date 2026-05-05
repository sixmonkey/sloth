<?php

/**
 * Pest Test Suite Configuration
 *
 * @since 1.1.0
 */

use Brain\Monkey;
use Sloth\Core\Application;

beforeEach(function (): void {
    Monkey\setUp();
});

afterEach(function (): void {
    Monkey\tearDown();
});

/**
 * Create a minimal test Application for console tests.
 *
 * @return Application The configured test application.
 * @since 1.0.0
 */
function makeTestApp(): Application
{
    $app = new Application();
    $app->instance('config', new \Illuminate\Config\Repository([]));
    $app->instance('files', new \Illuminate\Filesystem\Filesystem());
    $app->instance('events', new \Illuminate\Events\Dispatcher($app));
    $app->instance('path.base', sys_get_temp_dir());
    $app->instance('path.app', sys_get_temp_dir() . '/app');
    $app->instance('path.theme', sys_get_temp_dir() . '/theme');
    $app->instance('path.cache', sys_get_temp_dir() . '/cache');

    return $app;
}
