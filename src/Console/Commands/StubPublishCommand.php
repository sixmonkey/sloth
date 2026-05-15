<?php

declare(strict_types=1);
namespace Sloth\Console\Commands;

use Sloth\Console\Command;

/**
 * Publish all Sloth stubs to the project for customisation.
 *
 * Once published, `make:*` commands will use the local stubs
 * instead of the framework defaults.
 *
 * ```bash
 * wp sloth stub:publish
 * wp sloth stub:publish --force  # overwrite existing stubs
 * ```
 *
 * @since 1.0.0
 */
class StubPublishCommand extends Command
{
    protected $signature = 'stub:publish
        {--force : Overwrite existing stubs}';

    protected $description = 'Publish Sloth stubs to the project for customisation';

    public function handle(): int
    {
        $source = dirname(__DIR__, 3) . '/resources/stubs';
        $dest   = app()->path('stubs');

        if (!is_dir($source)) {
            $this->error('Stubs directory not found: ' . $source);

            return self::FAILURE;
        }

        if (!is_dir($dest)) {
            mkdir($dest, 0o755, true);
        }

        $published = 0;
        $skipped = 0;

        foreach (glob($source . '/*') as $file) {
            $filename = basename($file);
            $target = $dest . '/' . $filename;

            if (file_exists($target) && !$this->option('force')) {
                $this->line("<fg=yellow>Skipped:</> {$filename}");
                $skipped++;

                continue;
            }

            copy($file, $target);
            $this->info("Published: {$filename}");
            $published++;
        }

        if ($published > 0) {
            $this->line('');
            $this->info("{$published} stub(s) published to stubs/");
        }

        if ($skipped > 0) {
            $this->line("<fg=yellow>{$skipped} stub(s) skipped — use --force to overwrite.</>");
        }

        return self::SUCCESS;
    }
}
