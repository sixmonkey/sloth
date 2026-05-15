<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;

/**
 * Generate a new API Controller.
 *
 * @since 1.0.0
 */
class MakeApiControllerCommand extends MakeCommand
{
    protected $signature = 'make:api-controller {name : The controller name}';

    protected $description = 'Create a new API Controller';

    protected function stub(): string
    {
        return 'ApiController.php.stub';
    }

    protected function destination(): string
    {
        return app()->path();
    }

    protected function baseNamespace(): string
    {
        return 'Api';
    }

    protected function outputPath(string $name): string
    {
        return 'Api/' . $this->resolveClass($name) . '.php';
    }

    #[Override]
    protected function replacements(string $name): array
    {
        $class = $this->resolveClass($name);
        $base = str_replace(['Controller', 'controller'], '', $class);

        return [
            '{{ api_namespace }}' => 'app/v1',
            '{{ rest_base }}'     => Str::kebab(Str::plural($base)),
        ];
    }
}
