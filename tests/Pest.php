<?php

declare(strict_types=1);

// IMPORTANT: app() function must be defined FIRST in global namespace
// Corcel checks app()->version(), this stubs it for standalone testing
use Illuminate\Container\Container;

if (!function_exists('app')) {
    function app() {
        static $stub = null;
        if ($stub === null) {
            $stub = new class extends Container {
                public function version(): string
                {
                    return 'Laravel 11.0';
                }
            };
            Container::setInstance($stub);
        }
        return Container::getInstance();
    }
}

use Brain\Monkey;

beforeEach(function (): void {
    monkeySetUpDatabase();
});

afterEach(function (): void {
    monkeyTearDownDatabase();
});