<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;

/**
 * Generate a new Model.
 *
 * @since 1.0.0
 */
class MakeModelCommand extends MakeCommand
{
    protected $signature = 'make:model {name : The model name}';

    protected $description = 'Create a new Model';

    protected function stub(): string
    {
        return 'Model.php.stub';
    }

    protected function destination(): string
    {
        return app()->path();
    }

    protected function baseNamespace(): string
    {
        return 'Model';
    }

    protected function outputPath(string $name): string
    {
        return 'Model/' . Str::studly(basename($name)) . '.php';
    }

    protected function replacements(string $name): array
    {
        $class = Str::studly(basename($name));

        return [
            '{{ post_type }}' => Str::snake($class),
            '{{ singular }}'  => Str::headline($class),
            '{{ plural }}'    => Str::headline(Str::plural($class)),
        ];
    }
}
