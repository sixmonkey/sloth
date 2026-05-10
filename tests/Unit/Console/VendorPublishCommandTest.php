<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Support\ServiceProvider;

/**
 * Dummy provider used to register publishable paths in tests.
 * publishes() is protected in Illuminate — this exposes it.
 */
class TestPublishProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void {}

    public function addPublishes(array $paths, ?string $tag = null): void
    {
        $this->publishes($paths, $tag);
    }
}

/**
 * Register publishable paths via a dummy provider instance.
 */
function registerPublishes(array $paths, ?string $tag = null): void
{
    $provider = new TestPublishProvider(makeTestApp());
    $provider->addPublishes($paths, $tag);
}

/**
 * Tests for Sloth\Console\Commands\VendorPublishCommand.
 *
 * @since 1.0.0
 */
describe('VendorPublishCommand', function (): void {
    // Reset ServiceProvider publish registry before and after each test.
    beforeEach(function (): void {
        $reflection = new \ReflectionProperty(ServiceProvider::class, 'publishes');
        $reflection->setValue(null, []);

        $reflection = new \ReflectionProperty(ServiceProvider::class, 'publishGroups');
        $reflection->setValue(null, []);
    });

    afterEach(function (): void {
        $reflection = new \ReflectionProperty(ServiceProvider::class, 'publishes');
        $reflection->setValue(null, []);

        $reflection = new \ReflectionProperty(ServiceProvider::class, 'publishGroups');
        $reflection->setValue(null, []);
    });

    describe('nothing to publish', function (): void {
        it('warns and returns 0 when no paths are registered', function (): void {
            $status = makeTestKernel()->discoverCommands()->handle(['vendor:publish'], []);

            expect($status)->toBe(0);
        });
    });

    describe('copying files', function (): void {
        it('copies a file to its target', function (): void {
            $from = tempnam(sys_get_temp_dir(), 'sloth_from_');
            $to   = sys_get_temp_dir() . '/sloth_to_' . uniqid() . '.php';

            file_put_contents($from, '<?php return [];');

            registerPublishes([$from => $to]);

            makeTestKernel()->discoverCommands()->handle(['vendor:publish'], []);

            expect(file_exists($to))->toBeTrue();
            expect(file_get_contents($to))->toBe('<?php return [];');

            unlink($from);
            unlink($to);
        });

        it('creates parent directories if they do not exist', function (): void {
            $from = tempnam(sys_get_temp_dir(), 'sloth_from_');
            $to   = sys_get_temp_dir() . '/sloth_nested_' . uniqid() . '/config/file.php';

            file_put_contents($from, '<?php return [];');

            registerPublishes([$from => $to]);

            makeTestKernel()->discoverCommands()->handle(['vendor:publish'], []);

            expect(file_exists($to))->toBeTrue();

            unlink($from);
            unlink($to);
            rmdir(dirname($to));
            rmdir(dirname(dirname($to)));
        });
    });

    describe('--force flag', function (): void {
        it('skips existing files without --force', function (): void {
            $from = tempnam(sys_get_temp_dir(), 'sloth_from_');
            $to   = tempnam(sys_get_temp_dir(), 'sloth_to_');

            file_put_contents($from, 'new content');
            file_put_contents($to, 'original content');

            registerPublishes([$from => $to]);

            makeTestKernel()->discoverCommands()->handle(['vendor:publish'], []);

            expect(file_get_contents($to))->toBe('original content');

            unlink($from);
            unlink($to);
        });

        it('overwrites existing files with --force', function (): void {
            $from = tempnam(sys_get_temp_dir(), 'sloth_from_');
            $to   = tempnam(sys_get_temp_dir(), 'sloth_to_');

            file_put_contents($from, 'new content');
            file_put_contents($to, 'original content');

            registerPublishes([$from => $to]);

            makeTestKernel()->discoverCommands()->handle(['vendor:publish'], ['force' => true]);

            expect(file_get_contents($to))->toBe('new content');

            unlink($from);
            unlink($to);
        });
    });

    describe('--tag filter', function (): void {
        it('only publishes files matching the given tag', function (): void {
            $fromConfig = tempnam(sys_get_temp_dir(), 'sloth_config_');
            $toConfig   = sys_get_temp_dir() . '/sloth_to_config_' . uniqid() . '.php';

            $fromViews = tempnam(sys_get_temp_dir(), 'sloth_views_');
            $toViews   = sys_get_temp_dir() . '/sloth_to_views_' . uniqid() . '.php';

            registerPublishes([$fromConfig => $toConfig], 'config');
            registerPublishes([$fromViews => $toViews], 'views');

            makeTestKernel()->discoverCommands()->handle(['vendor:publish'], ['tag' => 'config']);

            expect(file_exists($toConfig))->toBeTrue();
            expect(file_exists($toViews))->toBeFalse();

            unlink($fromConfig);
            unlink($fromViews);
            unlink($toConfig);
        });
    });

    describe('return codes', function (): void {
        it('returns 0 on success', function (): void {
            $from = tempnam(sys_get_temp_dir(), 'sloth_from_');
            $to   = sys_get_temp_dir() . '/sloth_to_' . uniqid() . '.php';

            registerPublishes([$from => $to]);

            $status = makeTestKernel()->discoverCommands()->handle(['vendor:publish'], []);

            expect($status)->toBe(0);

            unlink($from);
            unlink($to);
        });
    });
});
