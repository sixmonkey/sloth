<?php

declare(strict_types=1);

namespace Sloth\Console\Commands;

use Sloth\Console\Command;

/**
 * Clears all Sloth manifest files from the cache directory.
 *
 * Manifests are discovered via convention — any `.php` file in the
 * `cache/Manifest/` directory is treated as a Sloth manifest and
 * deleted. This means new builders are picked up automatically
 * without updating this command.
 *
 * @since 1.0.0
 */
class ClearManifestsCommand extends Command
{
    /**
     * @since 1.0.0
     */
    protected $signature = 'manifest:clear';

    /**
     * @since 1.0.0
     */
    protected $description = 'Clear all Sloth manifest files — regenerated on next request';

    /**
     * Execute the command.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        $cachePath = app()->path('cache');
        $cleared   = 0;

        $manifests = app('files')->glob($cachePath . '/Manifest/*.php') ?: [];

        foreach ($manifests as $path) {
            $this->info("Clearing {$path}");

            app('files')->delete($path);
            $this->line("  <fg=green>✓</> Deleted " . basename($path));
            $cleared++;
        }

        $this->newLine();

        if ($cleared > 0) {
            $this->info("Cleared {$cleared} manifest(s). Regenerated on next request.");
        } else {
            $this->warn('No manifests found to clear.');
        }

        return self::SUCCESS;
    }
}
