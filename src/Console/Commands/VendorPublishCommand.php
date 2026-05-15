<?php

declare(strict_types=1);
namespace Sloth\Console\Commands;

use Illuminate\Support\ServiceProvider;
use Sloth\Console\Command;

/**
 * Publishes config and view files from a package into the project.
 *
 * Packages declare publishable files in their ServiceProvider::boot()
 * using the inherited publishes() method:
 *
 * ```php
 * public function boot(): void
 * {
 *     $this->publishes([
 *         __DIR__ . '/../config/my-package.php' => app()->configPath('my-package.php'),
 *     ], 'config');
 *
 *     $this->publishes([
 *         __DIR__ . '/../views/' => app()->themePath('View/vendor/my-package'),
 *     ], 'views');
 * }
 * ```
 *
 * Then publish via WP-CLI:
 *
 * ```bash
 * wp sloth vendor:publish --provider="MyPackage\MyServiceProvider"
 * wp sloth vendor:publish --tag=config
 * wp sloth vendor:publish --force
 * ```
 *
 * @since 1.0.0
 */
class VendorPublishCommand extends Command
{
    /**
     * @since 1.0.0
     */
    protected $signature = 'vendor:publish
        {--provider= : Publish only from this service provider (fully qualified class name)}
        {--tag=      : Publish only files registered under this tag}
        {--force     : Overwrite existing files}';

    /**
     * @since 1.0.0
     */
    protected $description = 'Publish config and view files from a package into the project';

    /**
     * Execute the command.
     *
     * Resolves publishable paths from Illuminate's ServiceProvider registry,
     * optionally filtered by provider class or tag. Copies each file to its
     * target, creating directories as needed.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        // Resolve publishable paths — optionally filtered by provider or tag.
        // ServiceProvider::pathsToPublish() reads from the static $publishes
        // registry populated by each provider's boot() method.
        $paths = ServiceProvider::pathsToPublish(
            $this->option('provider'),
            $this->option('tag'),
        );

        if (empty($paths)) {
            $this->warn('Nothing to publish.');
            $this->line('Make sure the provider is registered and calls $this->publishes() in boot().');

            return self::SUCCESS;
        }

        $published = 0;
        $skipped = 0;

        foreach ($paths as $from => $to) {
            // Skip if the target already exists and --force was not passed.
            if (file_exists($to) && !$this->option('force')) {
                $this->line('  <fg=yellow>SKIP</> ' . $this->relativePath($to) . ' (use --force to overwrite)');
                $skipped++;

                continue;
            }

            // Create parent directories if they don't exist.
            if (!is_dir(dirname((string) $to))) {
                mkdir(dirname((string) $to), 0o755, true);
            }

            if (!file_exists($from)) {
                $this->warn("  SKIP {$this->relativePath((string) $to)} (source file not found: {$from})");
                $skipped++;

                continue;
            }

            copy($from, $to);
            $this->line('  <fg=green>✓</> ' . $this->relativePath($to));
            $published++;
        }

        $this->newLine();

        if ($published > 0) {
            $this->info("Published {$published} file(s).");
        }

        if ($skipped > 0) {
            $this->warn("{$skipped} file(s) skipped — already exist. Run with --force to overwrite.");
        }

        return self::SUCCESS;
    }

    /**
     * Return a path relative to the project root for cleaner output.
     *
     * Falls back to the absolute path if the path is outside the project root.
     *
     * @since 1.0.0
     *
     * @param string $path
     */
    private function relativePath(string $path): string
    {
        $base = app()->path();

        if (str_starts_with($path, (string) $base)) {
            return ltrim(substr($path, strlen((string) $base)), '/');
        }

        return $path;
    }
}
