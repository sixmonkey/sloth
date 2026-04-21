<?php

declare(strict_types=1);

namespace Sloth\Console\Commands;

use Illuminate\Console\Command;

/**
 * Clears all Sloth manifest files from the cache directory.
 *
 * Manifests are regenerated automatically on the next request.
 *
 * @since 1.0.0
 */
class ClearManifestsCommand extends Command
{
    /**
     * @since 1.0.0
     */
    protected $signature = 'manifest:clear {--all : Also clear the illuminate file cache}';

    /**
     * @since 1.0.0
     */
    protected $description = 'Clear all Sloth manifest files — regenerated on next request';

    /**
     * Manifest filenames managed by Sloth.
     *
     * @var list<string>
     * @since 1.0.0
     */
    protected array $manifests = [
        'models.manifest.php',
        'taxonomies.manifest.php',
        'modules.manifest.php',
        'includes.manifest.php',
        'providers.manifest.php',
    ];

    /**
     * Execute the command.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        $cachePath = app()->path('cache');
        $cleared   = 0;

        foreach ($this->manifests as $manifest) {
            $path = $cachePath . '/' . $manifest;

            if (app('files')->exists($path)) {
                app('files')->delete($path);
                $this->line("  <fg=green>✓</> Deleted {$manifest}");
                $cleared++;
            } else {
                $this->line("  <fg=gray>–</> {$manifest} not found");
            }
        }

        if ($cleared > 0) {
            $this->newLine();
            $this->info("Cleared {$cleared} manifest(s). They will be regenerated on next request.");
        } else {
            $this->warn('No manifests found to clear.');
        }

        return self::SUCCESS;
    }
}
