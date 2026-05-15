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

    #[Override]
    protected function stub(): string
    {
        return 'Module.php.stub';
    }

    #[Override]
    protected function destination(): string
    {
        return app()->themePath();
    }

    #[Override]
    protected function classSuffix(): string
    {
        return 'Module';
    }

    #[Override]
    protected function outputPath(string $name): string
    {
        $class = $this->resolveClass($name);

        return "Module/{$class}.php";
    }

    #[Override]
    protected function replacements(string $name): array
    {
        $class = $this->resolveClass($name);

        return [
            '{{ id }}' => Str::kebab(Str::replaceLast('Module', '', $class)),
        ];
    }

    #[Override]
    public function handle(): int
    {
        $result = parent::handle();

        if ($result === self::SUCCESS) {
            $name = $this->argument('name');
            $class = $this->resolveClass($name);
            $id = Str::kebab(Str::replaceLast('Module', '', $class));

            $viewDir = app()->themePath('View/Module');
            $viewPath = "{$viewDir}/{$id}.twig";

            if (!is_dir($viewDir)) {
                mkdir($viewDir, 0o755, true);
            }

            if (!file_exists($viewPath)) {
                $stub = str_replace(
                    ['{{ id }}', '{{ class }}'],
                    [$id, $class],
                    $this->resolveStubByName('Module.twig.stub'),
                );
                file_put_contents($viewPath, $stub);

                $relative = str_replace(app()->appPath() . '/', '', $viewPath);
                $this->info("Created: {$relative}");
            }
        }

        return $result;
    }

    #[Override]
    protected function namespace(string $name): string
    {
        return 'Theme\\Module';
    }

    protected function resolveStubByName(string $stubName): string
    {
        $custom = app()->appPath('stubs') . '/' . $stubName;

        if (file_exists($custom)) {
            return file_get_contents($custom);
        }

        return file_get_contents(dirname(__DIR__, 4) . '/resources/stubs/' . $stubName);
    }
}
