<?php

declare(strict_types=1);

namespace Sloth\Filesystem;

use Illuminate\Filesystem\Filesystem;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Filesystem component.
 *
 * Registers `Illuminate\Filesystem\Filesystem` in the container under
 * the `'files'` key, making it available via `app('files')` and the
 * `File` facade throughout the framework and theme code.
 *
 * ## What it provides
 *
 * - `app('files')` — the `Illuminate\Filesystem\Filesystem` instance
 * - `File::exists()`, `File::get()`, `File::allFiles()` etc. via the Facade
 *
 * ## Why illuminate/filesystem
 *
 * PHP's native filesystem functions are procedural, inconsistent in their
 * return types, and hard to mock in tests. `illuminate/filesystem` wraps
 * them in a clean OOP API that is consistent, testable, and familiar to
 * Laravel developers.
 *
 * ## Registration
 *
 * Registered as the first framework provider in `Core\Sloth::registerProviders()`
 * so that `app('files')` is available to all subsequent providers,
 * including `ClassResolver` (for file scanning) and config loading.
 *
 * ## Usage in framework code
 *
 * ```php
 * // Via container
 * app('files')->allFiles(app()->path('app'));
 *
 * // Via Facade (requires 'File' alias in Core\Sloth::$classAliases)
 * File::exists(app()->path('app') . '/config.php');
 * File::getRequire(app()->path('app') . '/config/theme.php');
 * ```
 *
 * ## Usage in theme code
 *
 * ```php
 * // Without use statement (via class alias)
 * File::exists(get_template_directory() . '/custom.php');
 * ```
 *
 * @since 1.0.0
 * @see \Illuminate\Filesystem\Filesystem For all available methods
 * @see \Sloth\Facades\File For the static Facade interface
 */
class FilesystemServiceProvider extends ServiceProvider
{
    /**
     * Register the Filesystem service in the container.
     *
     * Binds `Illuminate\Filesystem\Filesystem` as a singleton under the
     * `'files'` key — the same key used by Laravel itself, ensuring
     * compatibility with any illuminate/* package that resolves `'files'`.
     *
     * Registered as a singleton because there is no state per-request —
     * the same instance can be safely shared across the entire application.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton('files', fn() => new Filesystem());
    }
}
