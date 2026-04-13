<?php

/**
 * Pest Test Suite Configuration
 *
 * @since 1.1.0
 */

use Brain\Monkey;

beforeEach(function (): void {
    Monkey\setUp();
});

afterEach(function (): void {
    Monkey\tearDown();
});
