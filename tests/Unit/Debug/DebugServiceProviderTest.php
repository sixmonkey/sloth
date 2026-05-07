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
 *
 * @since 1.0.0
 */
describe('DebugServiceProvider', function (): void {
    it('sets enabled to true and binds SlothDebugBar when DebugBar exists', function (): void {
        $app = Application::configure();
        Container::setInstance($app);

        $provider = new class ($app) extends DebugServiceProvider {
            public function register(): void
            {
                $this->app->singleton(SlothDebugBar::class);
                $this->app->alias(SlothDebugBar::class, 'debugbar');
                $this->app->alias(SlothDebugBar::class, DebugBar::class);
                $this->enabled = true;
            }
        };

        $provider->register();

        $reflection = new \ReflectionClass($provider);
        $property = $reflection->getProperty('enabled');

        expect($property->getValue($provider))->toBeTrue();
    });

    it('binds SlothDebugBar under debugbar alias', function (): void {
        $app = Application::configure();
        Container::setInstance($app);

        $provider = new class ($app) extends DebugServiceProvider {
            public function register(): void
            {
                $this->app->singleton(SlothDebugBar::class);
                $this->app->alias(SlothDebugBar::class, 'debugbar');
                $this->app->alias(SlothDebugBar::class, DebugBar::class);
                $this->enabled = true;
            }
        };

        $provider->register();

        expect($app->isAlias('debugbar'))->toBeTrue();
    });
});

describe('handleBootError()', function (): void {
    it('logs error message to application log', function (): void {
        $app = Application::configure();
        Container::setInstance($app);

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

        $exception = new \RuntimeException('Boot failed for testing');
        $method->invoke($provider, $exception);

        expect($logMock->logged)->toHaveCount(1);
        expect($logMock->logged[0]['message'])->toBe('Sloth DebugBar boot failed');
    });

    it('includes exception message in log context', function (): void {
        $app = Application::configure();
        Container::setInstance($app);

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

        $method->invoke($provider, new \RuntimeException('Boot failed for testing'));

        expect($logMock->logged[0]['context'])->toHaveKey('exception');
        expect($logMock->logged[0]['context']['exception'])->toBe('Boot failed for testing');
    });
});
