<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;

/**
 * Generate a new WP-CLI Command.
 *
 * @since 1.0.0
 */
class MakeCommandCommand extends MakeCommand
{
    protected $signature = 'make:command {name : The command class name}';

    protected $description = 'Create a new WP-CLI Command';

    #[Override]
    protected function stub(): string
    {
        return 'Command.php.stub';
    }

    #[Override]
    protected function classSuffix(): string
    {
        return 'Command';
    }

    #[Override]
    protected function destination(): string
    {
        return app()->appPath();
    }

    #[Override]
    protected function outputPath(string $name): string
    {
        return 'Console/Commands/' . $this->resolveClass($name) . '.php';
    }

    #[Override]
    protected function replacements(string $name): array
    {
        $class = $this->resolveClass($name);
        $base = Str::replaceLast('Command', '', $class);

        return [
            '{{ signature }}'   => 'app:' . Str::kebab($base),
            '{{ description }}' => '',
        ];
    }
}
