<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;

/**
 * Generate a new Service Provider.
 *
 * @since 1.0.0
 */
class MakeProviderCommand extends MakeCommand
{
    protected $signature = 'make:provider
        {name : The provider name}
        {--theme : Create in the theme directory}
        {--app : Create in the app directory}';

    protected $description = 'Create a new Service Provider';

    #[Override]
    protected function stub(): string
    {
        return 'Provider.php.stub';
    }

    #[Override]
    protected function classSuffix(): string
    {
        return 'Provider';
    }

    #[Override]
    protected function destination(): string
    {
        if ($this->option('theme') && $this->option('app')) {
            $this->error('Cannot use both --theme and --app.');
            exit(self::FAILURE);
        }

        if ($this->option('theme')) {
            return app()->themePath();
        }

        return app()->appPath();
    }

    #[Override]
    protected function outputPath(string $name): string
    {
        return 'Providers/' . $this->resolveClass($name) . '.php';
    }
}
