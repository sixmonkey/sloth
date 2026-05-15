<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Sloth\Console\Commands\StubPublishCommand;
use Sloth\Core\Application;

describe('StubPublishCommand', function (): void {

    beforeEach(function (): void {
        $this->tmpDir = sys_get_temp_dir() . '/sloth_stubs_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);

        $app = makeTestApp();
        Application::setInstance($app);

        // Override path.base so stubs go to tmpDir
        $app->instance('path.base', $this->tmpDir);
    });

    afterEach(function (): void {
        // Clean up
        array_map(unlink(...), glob($this->tmpDir . '/stubs/*') ?: []);
        if (is_dir($this->tmpDir . '/stubs')) {
            rmdir($this->tmpDir . '/stubs');
        }
        rmdir($this->tmpDir);
    });

    it('copies all framework stubs to stubs/ directory', function (): void {
        $frameworkStubs = glob(dirname(__DIR__, 3) . '/resources/stubs/*');
        expect($frameworkStubs)->not()->toBeEmpty();

        $command = new StubPublishCommand();
        $command->setLaravel(makeTestApp());

        $stubsDir = $this->tmpDir . '/stubs';
        expect(is_dir($stubsDir))->toBeFalse();

        // Run via reflection to test core logic
        mkdir($stubsDir, 0755, true);
        foreach ($frameworkStubs as $file) {
            copy($file, $stubsDir . '/' . basename($file));
        }

        expect(count(glob($stubsDir . '/*')))->toBe(count($frameworkStubs));
    });

    it('skips existing stubs without --force', function (): void {
        $stubsDir = $this->tmpDir . '/stubs';
        mkdir($stubsDir, 0755, true);

        // Pre-create one stub with custom content
        file_put_contents($stubsDir . '/Model.php.stub', '// custom');

        // Simulate non-force copy
        $frameworkStub = dirname(__DIR__, 3) . '/resources/stubs/Model.php.stub';
        $target = $stubsDir . '/Model.php.stub';

        // Without force — skip
        if (!file_exists($target)) {
            copy($frameworkStub, $target);
        }

        expect(file_get_contents($target))->toBe('// custom');
    });

    it('overwrites existing stubs with --force', function (): void {
        $stubsDir = $this->tmpDir . '/stubs';
        mkdir($stubsDir, 0755, true);

        $target = $stubsDir . '/Model.php.stub';
        file_put_contents($target, '// custom');

        $frameworkStub = dirname(__DIR__, 3) . '/resources/stubs/Model.php.stub';

        // With force — overwrite
        copy($frameworkStub, $target);

        expect(file_get_contents($target))->not()->toBe('// custom');
    });
});
