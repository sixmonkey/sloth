<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;
use Sloth\Utility\Utility;

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
        return app()->basePath();
    }

    protected function baseNamespace(): string
    {
        return 'Api';
    }

    protected function outputPath(string $name): string
    {
        return 'Api/' . Str::studly(basename($name)) . '.php';
    }

    #[Override]
    protected function replacements(string $name): array
    {
        $class = Str::studly(basename($name));

        return [
            '{{ api_namespace }}' => config('app.wp_json.base_url', 'wp-json'),
            '{{ prefix }}'     => Utility::viewize($class),
        ];
    }
}
