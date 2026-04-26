<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Debug;

use Sloth\Debug\ExceptionHandler;
use ReflectionClass;

/**
 * Unit tests for the ExceptionHandler class.
 */
describe('ExceptionHandler', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $handler = new ExceptionHandler();
            expect($handler)->toBeInstanceOf(ExceptionHandler::class);
        });
    });

    describe('report()', function (): void {
        it('logs exception via log manager', function (): void {
            $handler = new ExceptionHandler();

            $exception = new \RuntimeException('Test error');

            $handler->report($exception);
        })->skip('Requires WordPress environment');
    });

    describe('shouldReport()', function (): void {
        it('returns true by default', function (): void {
            $handler = new ExceptionHandler();
            $exception = new \RuntimeException('Test');

            expect($handler->shouldReport($exception))->toBeTrue();
        });
    });

    describe('render()', function (): void {
        it('method exists', function (): void {
            $handler = new ExceptionHandler();
            $exception = new \RuntimeException('Test');

            expect(method_exists($handler, 'render'))->toBeTrue();
        });
    });

    describe('renderForConsole()', function (): void {
        it('outputs exception message and trace', function (): void {
            $handler = new ExceptionHandler();
            $exception = new \RuntimeException('Test error');

            ob_start();
            $handler->renderForConsole(null, $exception);
            $output = ob_get_clean();

            expect($output)->toContain('Test error');
        });
    });

    describe('protected methods via reflection', function (): void {
        it('isAjaxRequest detects admin-ajax', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('isAjaxRequest');
            $method->setAccessible(true);

            $_SERVER['PHP_SELF'] = '/wp-admin/admin-ajax.php';
            expect($method->invoke($handler))->toBeTrue();
            $_SERVER['PHP_SELF'] = '/index.php';
        });

        it('isAjaxRequest detects async-upload', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('isAjaxRequest');
            $method->setAccessible(true);

            $_SERVER['PHP_SELF'] = '/wp-admin/async-upload.php';
            expect($method->invoke($handler))->toBeTrue();
            $_SERVER['PHP_SELF'] = '/index.php';
        });

        it('isAjaxRequest detects X-Requested-With header', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('isAjaxRequest');
            $method->setAccessible(true);

            $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
            expect($method->invoke($handler))->toBeTrue();
            unset($_SERVER['HTTP_X_REQUESTED_WITH']);
        });

        it('getStatusCode returns 500 for generic exceptions', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('getStatusCode');
            $method->setAccessible(true);

            $exception = new \RuntimeException('Test');
            expect($method->invoke($handler, $exception))->toBe(500);
        });

        it('getStatusCode uses getStatusCode method when available', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $method = $reflection->getMethod('getStatusCode');
            $method->setAccessible(true);

            $exception = new class extends \RuntimeException {
                public function getStatusCode(): int
                {
                    return 404;
                }
            };

            expect($method->invoke($handler, $exception))->toBe(404);
        });
    });

    describe('dontDebug property', function (): void {
        it('contains expected endpoints', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new ReflectionClass($handler);
            $property = $reflection->getProperty('dontDebug');
            $property->setAccessible(true);

            $dontDebug = $property->getValue($handler);

            expect($dontDebug)->toContain('admin-ajax.php');
            expect($dontDebug)->toContain('async-upload.php');
        });
    });
});