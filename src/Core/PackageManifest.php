<?php

namespace Sloth\Core;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Env;

class PackageManifest
{
    public string $vendorPath;

    public function __construct(
        public Filesystem $files,
        public string $basePath,
        ?string $manifestPath = null
    ) {
        $this->vendorPath = Env::get('COMPOSER_VENDOR_DIR') ?: $basePath . '/vendor';
        $packages = [];

        if ($this->files->exists($path = $this->vendorPath . '/composer/installed.json')) {
            $installed = json_decode($this->files->get($path), true);

            $packages = $installed['packages'] ?? $installed;
        }
        $foo = (new Collection($packages))
            ->filter(function ($package) {
                return ($package['extra'] && $package['extra']['laravel']);
            })
            ->mapWithKeys(function ($package) {
                return [$package['name'] => $package['extra']['laravel'] ?? []];
            });
    }
}
