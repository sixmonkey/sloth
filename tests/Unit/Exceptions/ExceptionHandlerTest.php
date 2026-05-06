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
            $app = makeTestApp();
            \Sloth\Core\Application::setInstance($app);

            $logMock = new class {
                public array $logged = [];

                public function error(string $message, array $context = []): void
                {
                    $this->logged[] = ['message' => $message, 'context' => $context];
                }
            };

            $app->instance('log', $logMock);

            $handler = new \Sloth\Exceptions\ExceptionHandler();
            $exception = new \RuntimeException('Test error');
            $handler->report($exception);

            expect($logMock->logged)->toHaveCount(1);
            expect($logMock->logged[0]['message'])->toBe('Test error');
        });
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

            $output = new \Symfony\Component\Console\Output\BufferedOutput();

            try {
                $handler->renderForConsole($output, $exception);
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
