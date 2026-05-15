<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Sloth\Console\Commands\Make\MakeCommand;
use Sloth\Core\Application;

/**
 * Concrete fixture implementation of MakeCommand for testing.
 */
class TestMakeCommand extends MakeCommand
{
    protected $signature = 'make:test {name}';
    protected $description = 'Test';

    protected function stub(): string { return 'Provider.php.stub'; }
    protected function outputPath(string $name): string { return 'Providers/' . $name . '.php'; }
    protected function destination(): string { return app()->path(); }
}

describe('MakeCommand', function (): void {

    describe('resolveStub()', function (): void {
        it('returns framework stub when no custom stub exists', function (): void {
            $app = makeTestApp();
            Application::setInstance($app);
            $app->instance('path.base', sys_get_temp_dir() . '/no_stubs_' . uniqid());

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'resolveStub');
            $stub = $reflection->invoke($command);

            expect($stub)->toContain('extends ServiceProvider');
        });

        it('prefers custom stub when it exists', function (): void {
            $tmpDir = sys_get_temp_dir() . '/sloth_custom_stub_' . uniqid();
            mkdir($tmpDir . '/stubs', 0755, true);
            file_put_contents($tmpDir . '/stubs/Provider.php.stub', '// custom stub');

            $app = makeTestApp();
            Application::setInstance($app);
            $app->instance('path.base', $tmpDir);

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'resolveStub');
            $stub = $reflection->invoke($command);

            expect($stub)->toBe('// custom stub');

            unlink($tmpDir . '/stubs/Provider.php.stub');
            rmdir($tmpDir . '/stubs');
            rmdir($tmpDir);
        });
    });

    describe('replaceStub()', function (): void {
        it('replaces {{ class }} and {{ namespace }} placeholders', function (): void {
            $app = makeTestApp();
            Application::setInstance($app);

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'replaceStub');
            $result = $reflection->invoke($command, 'namespace {{ namespace }}; class {{ class }}', 'MyProvider');

            expect($result)->toContain('class MyProvider');
            expect($result)->toContain('Providers');
        });

        it('handles nested name with subdirectory', function (): void {
            $app = makeTestApp();
            Application::setInstance($app);

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'replaceStub');
            $result = $reflection->invoke($command, 'namespace {{ namespace }}; class {{ class }}', 'Sub/MyProvider');

            expect($result)->toContain('class MyProvider');
            expect($result)->toContain('Providers\\Sub');
        });
    });

    describe('namespace()', function (): void {
        it('returns Theme namespace when destination is not app/', function (): void {
            $app = makeTestApp();
            Application::setInstance($app);

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'namespace');
            $result = $reflection->invoke($command, 'MyProvider');

            // TestMakeCommand::destination() returns app()->path() — not 'app'
            expect($result)->toContain('Providers');
        });

        it('includes subdirectory in namespace', function (): void {
            $app = makeTestApp();
            Application::setInstance($app);

            $command = new TestMakeCommand();
            $command->setLaravel($app);

            $reflection = new \ReflectionMethod($command, 'namespace');
            $result = $reflection->invoke($command, 'Auth/LoginProvider');

            expect($result)->toContain('Auth');
            expect($result)->toContain('Providers');
        });
    });
});
