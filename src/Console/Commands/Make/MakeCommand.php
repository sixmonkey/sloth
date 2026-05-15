<?php

declare(strict_types=1);
namespace Sloth\Console\Commands\Make;

use Illuminate\Support\Str;
use Sloth\Console\Command;

/**
 * Base class for all make commands.
 *
 * Handles stub loading, placeholder replacement and file creation.
 *
 * @since 1.0.0
 */
abstract class MakeCommand extends Command
{
    /**
     * Get the stub file name — e.g. 'Module.php'.
     *
     * @since 1.0.0
     */
    abstract protected function stub(): string;

    /**
     * Get the output path relative to the destination directory.
     *
     * @since 1.0.0
     *
     * @param string $name
     */
    abstract protected function outputPath(string $name): string;

    /**
     * Get the destination directory — App-Root or theme root.
     *
     * @since 1.0.0
     */
    abstract protected function destination(): string;

    /**
     * Get additional placeholder replacements.
     *
     * @since 1.0.0
     *
     * @param  string                $name
     * @return array<string, string>
     */
    protected function replacements(string $name): array
    {
        return [];
    }

    /**
     * Handle the command.
     *
     * @since 1.0.0
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $dest = $this->destination();
        $path = $dest . '/' . $this->outputPath($name);

        if (file_exists($path)) {
            $this->error("File already exists: {$path}");

            return self::FAILURE;
        }

        $stub = $this->resolveStub();
        $contents = $this->replaceStub($stub, $name);

        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($path, $contents);

        $relative = str_replace(app()->appPath() . '/', '', $path);
        $this->info("Created: {$relative}");

        return self::SUCCESS;
    }

    /**
     * Resolve the class name from the input name — applies classSuffix() correctly.
     *
     * Ensures the suffix is never doubled:
     * - make:module Foo       → FooModule
     * - make:module FooModule → FooModule (not FooModuleModule)
     *
     * @since 1.0.0
     *
     * @param string $name
     */
    protected function resolveClass(string $name): string
    {
        $class = Str::studly(basename($name));
        $suffix = $this->classSuffix();

        if ($suffix === '') {
            return $class;
        }

        return Str::replaceLast($suffix, '', $class) . $suffix;
    }

    /**
     * Resolve the stub file — custom stub takes precedence over framework stub.
     *
     * @since 1.0.0
     */
    protected function resolveStub(): string
    {
        $custom = app()->appPath('stubs') . '/' . $this->stub();

        if (file_exists($custom)) {
            return file_get_contents($custom);
        }

        return file_get_contents(dirname(__DIR__, 4) . '/resources/stubs/' . $this->stub());
    }

    /**
     * Replace all placeholders in the stub.
     *
     * @since 1.0.0
     *
     * @param string $stub
     * @param string $name
     */
    protected function replaceStub(string $stub, string $name): string
    {
        $class = $this->resolveClass($name);

        $replacements = array_merge([
            '{{ class }}'     => $class,
            '{{ namespace }}' => $this->namespace($name),
        ], $this->replacements($name));

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub,
        );
    }

    /**
     * Resolve the PHP namespace for the generated class.
     *
     * Derives the namespace from the destination path:
     * - If destination basename is 'app' → App\...
     * - Otherwise → Theme\...
     *
     * @since 1.0.0
     *
     * @param string $name
     */
    protected function namespace(string $name): string
    {
        $root = basename($this->destination()) === 'app' ? 'App' : 'Theme';
        $subPath = dirname($this->outputPath($name));

        if ($subPath === '.') {
            return $root;
        }

        $sub = collect(explode('/', $subPath))
            ->map(fn ($s) => Str::studly($s))
            ->join('\\')
        ;

        return $root . '\\' . $sub;
    }

    /**
     * Get a suffix for this class.
     *
     * Sometimes classes need a suffix (Module, Provider)
     *
     * @since 1.0.0
     */
    protected function classSuffix(): string
    {
        return '';
    }
}
