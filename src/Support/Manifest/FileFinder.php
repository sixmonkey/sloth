<?php

declare(strict_types=1);

namespace Sloth\Support\Manifest;

use Symfony\Component\Finder\Finder;

/**
 * Discovers plain PHP files via filesystem scan.
 *
 * Used for includes/ directories where files contain functions
 * rather than classes — ClassMapGenerator wouldn't find these.
 *
 * Returns [absolute_file_path => absolute_file_path].
 *
 * @since 1.0.0
 */
class FileFinder implements FinderInterface
{
    public function find(array $paths): array
    {
        $existingPaths = collect($paths)->filter(fn($path) => is_dir($path))->all();

        if (empty($existingPaths)) {
            return [];
        }

        return collect((new Finder())->in($existingPaths)->files()->name('*.php'))
            ->mapWithKeys(fn($file) => [
                $file->getRealPath() => $file->getRealPath(),
            ])
            ->all();
    }
}
