<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;

/**
 * Generate a new Service Provider.
 *
 * Use --theme to create in the theme directory,
 * --app to create in the app directory.
 * In Theme mode, both flags result in the same location.
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

    protected function stub(): string
    {
        return 'Provider.php.stub';
    }

    protected function destination(): string
    {
        if ($this->option('theme') && $this->option('app')) {
            $this->error('Cannot use both --theme and --app.');

            exit(self::FAILURE);
        }

        if ($this->option('theme')) {
            return app()->basePath('theme');
        }

        return app()->basePath();
    }

    protected function baseNamespace(): string
    {
        return 'Providers';
    }

    protected function outputPath(string $name): string
    {
        return 'Providers/' . Str::studly(basename($name)) . 'ServiceProvider.php';
    }

    #[Override]
    protected function classSuffix(): string
    {
        return 'ServiceProvider';
    }
}
