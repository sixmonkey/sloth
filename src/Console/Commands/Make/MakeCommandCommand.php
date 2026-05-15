<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;

/**
 * Generate a new WP-CLI Command.
 *
 * @since 1.0.0
 */
class MakeCommandCommand extends MakeCommand
{
    protected $signature = 'make:command {name : The command class name}';

    protected $description = 'Create a new WP-CLI Command';

    protected function stub(): string
    {
        return 'Command.php.stub';
    }

    protected function destination(): string
    {
        return app()->path();
    }

    protected function baseNamespace(): string
    {
        return 'Console\\Commands';
    }

    protected function outputPath(string $name): string
    {
        return 'Console/Commands/' . Str::studly(basename($name)) . '.php';
    }

    protected function replacements(string $name): array
    {
        $class = Str::studly(basename($name));

        return [
            '{{ signature }}'   => 'app:' . Str::kebab(str_replace('Command', '', $class)),
            '{{ description }}' => 'Start building',
        ];
    }
}
