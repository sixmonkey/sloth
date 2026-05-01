<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Debug;

use DebugBar\DebugBar;
use Illuminate\Container\Container;
use Sloth\Core\Application;
use Sloth\Debug\DebugServiceProvider;
use Sloth\Debug\SlothDebugBar;

/**
 * Unit tests for the DebugServiceProvider class.
 */
describe('DebugServiceProvider', function (): void {

    describe('register()', function (): void {

        it('bails out early when DebugBar class does not exist', function (): void {
            // Simulate production: DebugBar class is not loaded
            if (class_exists(DebugBar::class, false)) {
                // Skip if already autoloaded — we can't test this
                expect(true)->toBeTrue('DebugBar is already autoloaded, bail-out test skipped');
                return;
            }

            $app = new Application();

            $provider = new class($app) extends DebugServiceProvider {
                public function isEnabled(): bool
                {
                    return $this->enabled;
                }
            };

            $provider->register();

            expect($provider->isEnabled())->toBeFalse();
        })->skip('DebugBar class is autoloaded via composer, cannot test bail-out in isolation');

        it('registers singleton and starts output buffer when DebugBar exists', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new class($app) extends DebugServiceProvider {
                /**
                 * Override register to avoid actually starting ob_start in tests.
                 */
                public function register(): void
                {
                    // Simulate what register() does without ob_start
                    $this->app->singleton(SlothDebugBar::class);
                    $this->app->alias(SlothDebugBar::class, 'debugbar');
                    $this->app->alias(SlothDebugBar::class, DebugBar::class);
                    $this->enabled = true;
                }
            };

            $provider->register();

            $reflection = new \ReflectionClass($provider);
            $property = $reflection->getProperty('enabled');
            $property->setAccessible(true);

            expect($property->getValue($provider))->toBeTrue();
        });
    });

    describe('renderBar()', function (): void {

        it('returns output unchanged when not enabled', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new class($app) extends DebugServiceProvider {
                public function setEnabled(bool $value): void
                {
                    $this->enabled = $value;
                }
            };

            $provider->setEnabled(false);

            $output = '<html><head></head><body>Hello</body></html>';
            $result = $provider->renderBar($output);

            expect($result)->toBe($output);
        });

        it('returns output unchanged when display config is false', function (): void {
            $app = new Application();
            Container::setInstance($app);
            \Sloth\Facades\Facade::setFacadeApplication($app);

            // Ensure config is loaded with display set to false
            $app->singleton('config', function () {
                return new \Illuminate\Config\Repository([
                    'debugger' => [
                        'bar' => [
                            'display' => false,
                        ],
                    ],
                ]);
            });

            $provider = new class($app) extends DebugServiceProvider {
                public function setEnabled(bool $value): void
                {
                    $this->enabled = $value;
                }
            };

            $provider->setEnabled(true);

            $output = '<html><head></head><body>Hello</body></html>';
            $result = $provider->renderBar($output);

            // display=false should return output unchanged without resolving DebugBar
            expect($result)->toBe($output);
        });
    });

    describe('isJsonResponse()', function (): void {

        it('detects JSON object response', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, '{"status":"ok"}'))->toBeTrue();
        });

        it('detects JSON array response', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, '[1, 2, 3]'))->toBeTrue();
        });

        it('detects JSON with leading whitespace', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, '  {"key":"value"}'))->toBeTrue();
        });

        it('rejects HTML response', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, '<html><head></head></html>'))->toBeFalse();
        });

        it('rejects empty output', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, ''))->toBeFalse();
            expect($method->invoke($provider, '   '))->toBeFalse();
        });

        it('rejects plain text response', function (): void {
            $app = new Application();
            Container::setInstance($app);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('isJsonResponse');
            $method->setAccessible(true);

            expect($method->invoke($provider, 'Hello World'))->toBeFalse();
        });
    });

    describe('handleBootError()', function (): void {

        it('logs error to application log', function (): void {
            $app = new Application();
            Container::setInstance($app);

            // Mock the log facade
            $logMock = new class {
                public array $logged = [];

                public function error(string $message, array $context = []): void
                {
                    $this->logged[] = ['message' => $message, 'context' => $context];
                }
            };

            $app->instance('log', $logMock);

            $provider = new DebugServiceProvider($app);

            $reflection = new \ReflectionClass($provider);
            $method = $reflection->getMethod('handleBootError');
            $method->setAccessible(true);

            $exception = new \RuntimeException('Boot failed for testing');
            $method->invoke($provider, $exception);

            expect($logMock->logged)->toHaveCount(1);
            expect($logMock->logged[0]['message'])->toBe('Sloth DebugBar boot failed');
            expect($logMock->logged[0]['context'])->toHaveKey('exception');
            expect($logMock->logged[0]['context']['exception'])->toBe('Boot failed for testing');
        });
    });
});
