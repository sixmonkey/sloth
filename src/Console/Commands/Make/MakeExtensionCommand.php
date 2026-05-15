<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;

/**
 * Generate a new View Extension.
 *
 * @since 1.0.0
 */
class MakeExtensionCommand extends MakeCommand
{
    protected $signature = 'make:extension {name : The extension name}';

    protected $description = 'Create a new View Extension';

    protected function stub(): string
    {
        return 'Extension.php.stub';
    }

    protected function destination(): string
    {
        return app()->path();
    }

    protected function baseNamespace(): string
    {
        return 'Extensions\\View';
    }

    protected function outputPath(string $name): string
    {
        return 'Extensions/View/' . $this->resolveClass($name) . '.php';
    }
}
