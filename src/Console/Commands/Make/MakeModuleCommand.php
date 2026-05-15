<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Override;

/**
 * Generate a new Module.
 *
 * Always creates in the theme directory — modules are UI components.
 *
 * @since 1.0.0
 */
class MakeModuleCommand extends MakeCommand
{
    protected $signature = 'make:module {name : The module name}';

    protected $description = 'Create a new Module';

    protected function stub(): string
    {
        return 'Module.php.stub';
    }

    protected function destination(): string
    {
        return app()->path('Module', 'theme');
    }

    protected function baseNamespace(): string
    {
        return 'Module';
    }

    protected function outputPath(string $name): string
    {
        $class = Str::studly(basename($name));

        return "{$class}Module.php";
    }

    #[Override]
    protected function replacements(string $name): array
    {
        $class = Str::studly(basename($name));

        return [
            '{{ id }}' => Str::kebab($class),
        ];
    }

    #[Override]
    public function handle(): int
    {
        $result = parent::handle();

        if ($result === self::SUCCESS) {
            // Also create the Twig view
            $name = $this->argument('name');
            $class = Str::studly(basename($name));
            $id = Str::kebab($name);
            $viewDir = app()->path('View/Module', 'theme');

            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0o755, true);
            }

            $viewPath = "{$viewDir}/{$id}.twig";

            if (!file_exists($viewPath)) {
                $stub = str_replace(
                    ['{{ id }}', '{{ class }}', '{{ name }}'],
                    [$id, $class, $class],
                    $this->resolveStubByName('Module.twig.stub'),
                );
                file_put_contents($viewPath, $stub);

                $relative = str_replace(app()->path() . '/', '', $viewPath);
                $this->info("Created: {$relative}");
            }
        }

        return $result;
    }

    protected function resolveStubByName(string $stubName): string
    {
        $custom = app()->path('stubs') . '/' . $stubName;

        if (file_exists($custom)) {
            return file_get_contents($custom);
        }

        return file_get_contents(dirname(__DIR__, 4) . '/resources/stubs/' . $stubName);
    }

    #[Override]
    protected function namespace($name): string
    {
        return 'Theme\\Module';
    }

    #[Override]
    protected function classSuffix(): string
    {
        return 'Module';
    }
}
