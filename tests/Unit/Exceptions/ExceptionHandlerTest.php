<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Exceptions;

use Sloth\Exceptions\ExceptionHandler;

/**
 * Unit tests for the ExceptionHandler class.
 *
 * @since 1.0.0
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
        it('does not throw an exception', function (): void {
            $handler = new ExceptionHandler();
            $exception = new \RuntimeException('Test error');

            // renderForConsole uses Symfony ConsoleOutput which writes to STDERR
            // We just verify it doesn't throw an exception
            try {
                $handler->renderForConsole(null, $exception);
                expect(true)->toBeTrue();
            } catch (\Throwable $e) {
                expect(false)->toBeTrue('renderForConsole should not throw');
            }
        });
    });

    describe('protected methods via reflection', function (): void {
        it('getStatusCode returns 500 for generic exceptions', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new \ReflectionClass($handler);
            $method = $reflection->getMethod('getStatusCode');
            $method->setAccessible(true);

            $exception = new \RuntimeException('Test');
            expect($method->invoke($handler, $exception))->toBe(500);
        });

        it('getStatusCode uses getStatusCode method when available', function (): void {
            $handler = new ExceptionHandler();
            $reflection = new \ReflectionClass($handler);
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
});
