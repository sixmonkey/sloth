<?php

declare(strict_types=1);

namespace Sloth\Facades;

/**
 * File Facade — static access to the Filesystem service.
 *
 * Provides a convenient static interface to `illuminate/filesystem`,
 * which wraps PHP's native filesystem functions in a clean, testable API.
 *
 * ## Usage
 *
 * ```php
 * use Sloth\Facades\File;
 *
 * // Check existence
 * File::exists('/path/to/file.php');
 *
 * // Read a file
 * $contents = File::get('/path/to/file.php');
 *
 * // Write a file
 * File::put('/path/to/file.php', $contents);
 *
 * // Require a PHP file and return its value
 * $config = File::getRequire('/path/to/config.php');
 *
 * // List all files in a directory (recursive)
 * $files = File::allFiles('/path/to/dir');
 *
 * // List directories
 * $dirs = File::directories('/path/to/dir');
 *
 * // Delete a file
 * File::delete('/path/to/file.php');
 *
 * // Copy a file
 * File::copy('/source.php', '/destination.php');
 *
 * // Move a file
 * File::move('/source.php', '/destination.php');
 *
 * // Get file extension
 * File::extension('/path/to/file.php'); // 'php'
 *
 * // Get file size in bytes
 * File::size('/path/to/file.php');
 * ```
 *
 * ## Container binding
 *
 * The underlying `Illuminate\Filesystem\Filesystem` instance is bound
 * to the container as `'files'` by `FilesystemServiceProvider`.
 *
 * ## Class alias
 *
 * Registered in `Core\Sloth::$classAliases` as `'File'`, so it can be
 * used without the full namespace in theme code:
 *
 * ```php
 * File::exists($path); // works without use statement
 * ```
 *
 * @since 1.0.0
 * @see \Illuminate\Filesystem\Filesystem For all available methods
 * @see \Sloth\Filesystem\FilesystemServiceProvider For container registration
 *
 * @method static bool   exists(string $path)                                             Determine if a file or directory exists.
 * @method static string get(string $path, bool $lock = false)                            Get the contents of a file.
 * @method static mixed  getRequire(string $path, array $data = [])                       Get the returned value of a file.
 * @method static void   requireOnce(string $path, array $data = [])                      Require the given file once.
 * @method static bool   put(string $path, string $contents, bool $lock = false)          Write the contents of a file.
 * @method static bool   delete(string|array $paths)                                      Delete the file at a given path.
 * @method static bool   copy(string $path, string $target)                               Copy a file to a new location.
 * @method static bool   move(string $path, string $target)                               Move a file to a new location.
 * @method static string extension(string $path)                                          Extract the file extension from a path.
 * @method static int    size(string $path)                                               Get the file size of a given file.
 * @method static int    lastModified(string $path)                                       Get the file's last modification time.
 * @method static bool   isDirectory(string $directory)                                   Determine if the given path is a directory.
 * @method static bool   isFile(string $file)                                             Determine if the given path is a file.
 * @method static bool   makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false) Create a directory.
 * @method static bool   deleteDirectory(string $directory, bool $preserve = false)       Recursively delete a directory.
 * @method static array  files(string $directory, bool $hidden = false)                   Get an array of all files in a directory.
 * @method static array  allFiles(string $directory, bool $hidden = false)                Get all of the files from the given directory (recursive).
 * @method static array  directories(string $directory)                                   Get all of the directories within a given directory.
 */
class File extends Facade
{
    /**
     * Return the container binding key for the Filesystem service.
     *
     * The `Illuminate\Filesystem\Filesystem` instance is registered
     * under the `'files'` key by `FilesystemServiceProvider`.
     *
     * @return string The container binding key.
     * @since 1.0.0
     *
     */
    #[\Override]
    protected static function getFacadeAccessor(): string
    {
        return 'files';
    }
}
