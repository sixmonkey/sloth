<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Container\Container;
use Illuminate\Events\Dispatcher;
use Illuminate\Config\Repository;
use Sloth\Console\ConsoleKernel;

/**
 * Tests for Sloth\Console\ConsoleKernel.
 *
 * @since 1.0.0
 */
describe('ConsoleKernel', function (): void {
    it('creates a console application', function (): void {
        $container = new class extends Container {
            public function version(): string
            {
                return '1.0.0';
            }
        };
        $container->instance('app', $container);
        $container->instance('config', new Repository([]));
        $container->instance('files', new \Illuminate\Filesystem\Filesystem());
        $container->instance('events', new Dispatcher($container));
        
        $container->instance('path.base', '/tmp');
        $container->instance('path.app', '/tmp/app');
        $container->instance('path.cache', '/tmp/cache');
        
        $kernel = new ConsoleKernel($container);
        
        expect($kernel)->toBeInstanceOf(ConsoleKernel::class);
    });

    it('discovers framework commands without error', function (): void {
        $container = new class extends Container {
            public function version(): string
            {
                return '1.0.0';
            }
            
            public function path(string $path = '', string $prefix = 'app'): string
            {
                $base = $this->make('path.' . $prefix);
                return $path ? \Illuminate\Filesystem\join_paths($base, $path) : $base;
            }
        };
        $container->instance('app', $container);
        $container->instance('config', new Repository([]));
        $container->instance('files', new \Illuminate\Filesystem\Filesystem());
        $container->instance('events', new Dispatcher($container));
        
        $container->instance('path.base', '/tmp');
        $container->instance('path.app', '/tmp/app');
        $container->instance('path.cache', '/tmp/cache');
        
        $kernel = new ConsoleKernel($container);
        $kernel->discoverCommands();
        
        expect(true)->toBeTrue();
    });

    it('returns success for inspire command', function (): void {
        $container = new class extends Container {
            public function version(): string
            {
                return '1.0.0';
            }
            
            public function path(string $path = '', string $prefix = 'app'): string
            {
                $base = $this->make('path.' . $prefix);
                return $path ? \Illuminate\Filesystem\join_paths($base, $path) : $base;
            }
            
            public function runningUnitTests(): bool
            {
                return false;
            }
        };
        $container->instance('app', $container);
        $container->instance('config', new Repository([]));
        $container->instance('files', new \Illuminate\Filesystem\Filesystem());
        $container->instance('events', new Dispatcher($container));
        
        $container->instance('path.base', '/tmp');
        $container->instance('path.app', '/tmp/app');
        $container->instance('path.cache', '/tmp/cache');
        
        $kernel = new ConsoleKernel($container);
        $kernel->discoverCommands();
        $status = $kernel->handleArgv(['sloth', 'inspire']);
        
        expect($status)->toBe(0);
    });

    it('returns success for list command', function (): void {
        $container = new class extends Container {
            public function version(): string
            {
                return '1.0.0';
            }
            
            public function path(string $path = '', string $prefix = 'app'): string
            {
                $base = $this->make('path.' . $prefix);
                return $path ? \Illuminate\Filesystem\join_paths($base, $path) : $base;
            }
        };
        $container->instance('app', $container);
        $container->instance('config', new Repository([]));
        $container->instance('files', new \Illuminate\Filesystem\Filesystem());
        $container->instance('events', new Dispatcher($container));
        
        $container->instance('path.base', '/tmp');
        $container->instance('path.app', '/tmp/app');
        $container->instance('path.cache', '/tmp/cache');
        
        $kernel = new ConsoleKernel($container);
        $status = $kernel->handleArgv(['sloth', 'list']);
        
        expect($status)->toBe(0);
    });
});