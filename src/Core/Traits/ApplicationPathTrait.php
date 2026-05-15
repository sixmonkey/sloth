<?php

declare(strict_types=1);
namespace Sloth\Core\Traits;

use function Illuminate\Filesystem\join_paths;
use Deprecated;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;

/**
 * Manages all filesystem path and URI resolution for the application.
 *
 * Extracted from Application to keep that class focused on lifecycle
 * and provider management. This trait provides:
 *
 * - Path registration and resolution (registerBasePaths, guessBasePath)
 * - Typed path accessors (appPath, themePath, cachePath, etc.)
 * - URI registration and resolution (registerBaseUris)
 * - Typed URI accessor (uri)
 * - Mode detection (isThemeMode)
 *
 * ## Path structure
 *
 * ### Classic mode
 * ```
 * my-project/           ← basePath (project root)
 * ├── app/              ← appPath
 * │   ├── Model/
 * │   ├── Module/
 * │   └── config/       ← configPath
 * ├── storage/          ← storagePath
 * │   ├── cache/        ← cachePath
 * │   └── logs/         ← logsPath
 * ├── vendor/
 * └── public/
 *     └── wp-content/
 *         └── themes/
 *             └── my-theme/   ← themePath
 * ```
 *
 * ### Theme mode
 * ```
 * my-theme/             ← basePath = appPath = themePath
 * ├── Model/
 * ├── Module/
 * ├── config/           ← configPath
 * ├── storage/          ← storagePath
 * │   ├── cache/        ← cachePath
 * │   └── logs/         ← logsPath
 * └── vendor/
 * ```
 *
 * @since 1.0.0
 */
trait ApplicationPathTrait
{
    // -------------------------------------------------------------------------
    // Static path cache
    // -------------------------------------------------------------------------

    /**
     * Cached project root path — set once by guessBasePath().
     *
     * Shared across all instances. Once set it never changes within a
     * request, so walking the filesystem only happens once.
     *
     * @since 1.0.0
     */
    private static ?string $cachedBasePath = null;

    // -------------------------------------------------------------------------
    // Resolved path properties
    // -------------------------------------------------------------------------

    /**
     * The resolved project root path.
     *
     * - Classic mode: directory containing composer.json (e.g. /var/www/my-project)
     * - Theme mode:   get_template_directory() (e.g. /var/www/html/wp-content/themes/my-theme)
     *
     * Set by registerBasePaths() during boot.
     *
     * @since 1.0.0
     */
    public ?string $basePath = null;

    /**
     * The resolved theme directory path.
     *
     * Always get_template_directory() once WordPress is available.
     *
     * @since 2.0.0
     */
    private string $themePath;

    /**
     * The resolved application directory path.
     *
     * - Classic mode: basePath/app/  (Models, Modules, Providers live here)
     * - Theme mode:   themePath      (same as theme root)
     *
     * @since 2.0.0
     */
    private string $appPath;

    // -------------------------------------------------------------------------
    // Path registration
    // -------------------------------------------------------------------------

    /**
     * Set the cached base path explicitly.
     *
     * Called from Application::__construct() when a basePath is passed to
     * configure() — skips guessBasePath() entirely.
     *
     * @since 2.0.0
     *
     * @param string $path
     */
    private function setBasePath(string $path): void
    {
        self::$cachedBasePath = $path;
    }

    /**
     * Register all base filesystem paths for the application.
     *
     * Called during boot() after WordPress is available. Paths are stored
     * in the container under the `path.*` prefix and also accessible via
     * the typed path accessors (appPath(), themePath(), cachePath(), etc.).
     *
     * Registered container keys:
     * - `path.base`      — project root (where composer.json lives)
     * - `path.app`       — app directory (app/ in Classic, theme root in Theme)
     * - `path.vendor`    — vendor/ directory (always at project root)
     * - `path.framework` — Sloth src/ directory
     * - `path.cms`       — WordPress ABSPATH
     * - `path.plugins`   — WP_PLUGIN_DIR
     * - `path.theme`     — active theme directory
     * - `path.uploads`   — WordPress uploads base directory
     * - `path.storage`   — project root storage/ (auto-created)
     * - `path.cache`     — storage/cache/ (auto-created)
     * - `path.logs`      — storage/logs/ (auto-created)
     *
     * @since 1.0.0
     */
    protected function registerBasePaths(): void
    {
        $this->basePath = $this->guessBasePath();

        // Theme path — fall back to a reasonable default if WordPress isn't available yet
        $this->themePath = function_exists('get_template_directory')
            ? get_template_directory()
            : $this->basePath . '/wp-content/themes/folivoro';

        // App path — same as theme root in Theme mode, app/ subdir in Classic mode
        $this->appPath = $this->isThemeMode()
            ? $this->themePath
            : $this->basePath . '/app';

        // Register all paths in the container
        $this->addPath('base', $this->basePath);
        $this->addPath('app', $this->appPath);
        $this->addPath('vendor', $this->basePath . '/vendor');
        $this->addPath('framework', dirname(__DIR__, 2));
        $this->addPath('cms', ABSPATH);
        $this->addPath('plugins', WP_PLUGIN_DIR);
        $this->addPath('theme', $this->themePath);
        $this->addPath('uploads', wp_upload_dir()['basedir']);

        // Storage always lives at the project root — outside app/ and theme/
        // so it is never accidentally committed or deployed.
        // Add /storage to .gitignore.
        $storagePath = $this->basePath . '/storage';

        foreach ([$storagePath, $storagePath . '/cache', $storagePath . '/logs'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0o755, true);
            }
        }

        $this->addPath('storage', $storagePath);
        $this->addPath('cache', $storagePath . '/cache');
        $this->addPath('logs', $storagePath . '/logs');
    }

    /**
     * Register all base URIs for the application.
     *
     * Called during boot() after WordPress is available. URIs are stored
     * in the container under the `uri.*` prefix and accessible via uri().
     * Trailing slashes are stripped for consistency.
     *
     * Registered container keys:
     * - `uri.home`    — WordPress home URL (home_url('/'))
     * - `uri.theme`   — Active theme directory URI
     * - `uri.content` — WordPress content directory URI
     * - `uri.uploads` — WordPress uploads directory URI
     *
     * @since 1.0.0
     */
    protected function registerBaseUris(): void
    {
        if (!function_exists('home_url')) {
            return;
        }

        $this->addUri('home', home_url('/'));
        $this->addUri('theme', get_template_directory_uri());
        $this->addUri('content', content_url());
        $this->addUri('uploads', wp_upload_dir()['baseurl']);
    }

    /**
     * Guess the project root path (basePath).
     *
     * In Classic mode, basePath is the directory containing composer.json.
     * In Theme mode, basePath is get_template_directory().
     *
     * Note: basePath is the project ROOT — not the App-Root. The App-Root
     * (appPath) is computed from basePath in registerBasePaths().
     *
     * Resolution order:
     * 1. Already cached — return immediately (no filesystem walk)
     * 2. `SLOTH_BASE_PATH` constant — explicit override, used as-is
     * 3. Walk up from ABSPATH to find composer.json outside vendor/ → Classic mode
     * 4. Fall back to get_template_directory() → Theme mode
     *
     * @throws RuntimeException if the base path cannot be determined
     *
     * @since 1.0.0
     */
    protected function guessBasePath(): string
    {
        if (self::$cachedBasePath !== null) {
            return self::$cachedBasePath;
        }

        if (defined('SLOTH_BASE_PATH')) {
            return self::$cachedBasePath = rtrim((string) SLOTH_BASE_PATH, '/');
        }

        $dir = dirname(defined('ABSPATH') ? ABSPATH : __DIR__);

        while ($dir !== '/') {
            if (file_exists($dir . '/composer.json') && !str_contains($dir, '/vendor/')) {
                return self::$cachedBasePath = $dir;
            }

            $dir = dirname($dir);
        }

        if (function_exists('get_template_directory')) {
            return self::$cachedBasePath = get_template_directory();
        }

        throw new RuntimeException(
            'Sloth could not determine the project base path. '
            . 'Define SLOTH_BASE_PATH in wp-config.php if your structure is non-standard.',
        );
    }

    // -------------------------------------------------------------------------
    // Mode detection
    // -------------------------------------------------------------------------

    /**
     * Determine whether Sloth is running in Theme mode.
     *
     * In Theme mode, the project root and theme directory are the same.
     * Both paths are resolved via realpath() to handle symlinks.
     *
     * @since 2.0.0
     */
    public function isThemeMode(): bool
    {
        return realpath($this->basePath) === realpath($this->themePath);
    }

    // -------------------------------------------------------------------------
    // Path and URI helpers
    // -------------------------------------------------------------------------

    /**
     * Add a filesystem path to the container.
     *
     * If the directory exists, realpath() is used to resolve symlinks
     * and normalise the path (e.g. /var → /private/var on macOS).
     *
     * @param string $key  container key (stored as "path.{$key}")
     * @param string $path absolute filesystem path
     *
     * @since 1.0.0
     */
    public function addPath(string $key, string $path): void
    {
        if (is_dir($path)) {
            $path = realpath($path);
        }

        $this->instance('path.' . $key, $path);
    }

    /**
     * Add a URI to the container.
     *
     * Trailing slashes are stripped so callers can safely append paths
     * with or without a leading slash.
     *
     * @param string $key container key (stored as "uri.{$key}")
     * @param string $uri absolute URI
     *
     * @since 1.0.0
     */
    public function addUri(string $key, string $uri): void
    {
        $this->instance('uri.' . $key, rtrim($uri, '/'));
    }

    /**
     * Join the given paths together.
     *
     * Delegates to Illuminate's join_paths() helper. Accepts strings or
     * arrays — arrays are joined with '/' before being passed to join_paths().
     *
     * @param string|array|null $basePath base path
     * @param array|string $path optional sub-path to append
     *
     * @return string
     * @since 1.0.0
     */
    public function joinPaths(null|string|array $basePath, string|array $path = ''): string
    {
        if (is_array($basePath)) {
            $basePath = implode('/', $basePath);
        }

        if (is_array($path)) {
            $path = implode('/', $path);
        }

        return join_paths($basePath, $path);
    }

    // -------------------------------------------------------------------------
    // Typed path accessors
    // -------------------------------------------------------------------------

    /**
     * Get the project root path.
     *
     * @since 1.0.0
     *
     * @param array|string $path
     */
    public function basePath(string|array $path = ''): string
    {
        return $this->joinPaths($this->basePath, $path);
    }

    /**
     * Get the application directory path.
     *
     * - Classic mode: basePath/app/
     * - Theme mode:   themePath (same as project root)
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function appPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->appPath, $path);
    }

    /**
     * Get the active theme directory path.
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function themePath(string|array $path = ''): string
    {
        return $this->joinPaths($this->themePath, $path);
    }

    /**
     * Get the config directory path.
     *
     * Config files live in app/ (or theme root) under config/.
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function configPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->appPath . '/config', $path);
    }

    /**
     * Get the storage directory path.
     *
     * Storage always lives at the project root — never inside app/ or the theme.
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function storagePath(string|array $path = ''): string
    {
        return $this->joinPaths($this->basePath . '/storage', $path);
    }

    /**
     * Get the cache directory path (storage/cache/).
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function cachePath(string|array $path = ''): string
    {
        return $this->joinPaths($this->basePath . '/storage/cache', $path);
    }

    /**
     * Get the logs directory path (storage/logs/).
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function logsPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->basePath . '/storage/logs', $path);
    }

    /**
     * Get the WordPress CMS root path (ABSPATH).
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function cmsPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->get('path.cms'), $path);
    }

    /**
     * Get the WordPress plugins directory path.
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function pluginsPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->get('path.plugins'), $path);
    }

    /**
     * Get the WordPress uploads base directory path.
     *
     * @since 2.0.0
     *
     * @param array|string $path
     */
    public function uploadsPath(string|array $path = ''): string
    {
        return $this->joinPaths($this->get('path.uploads'), $path);
    }

    // -------------------------------------------------------------------------
    // Legacy path accessor
    // -------------------------------------------------------------------------
    /**
     * Get a registered filesystem path from the container.
     *
     * @param string $path   optional sub-path to append
     * @param string $prefix path key — see registerBasePaths() for available keys
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @since 1.0.0
     */
    #[Deprecated(message: <<<'TXT'
        2.0 Use the typed path accessors instead:
                     appPath(), themePath(), cachePath(), storagePath() etc.
        TXT)]
    public function path(string $path = '', string $prefix = 'base'): string
    {
        return join_paths($this->get('path.' . $prefix), $path);
    }

    // -------------------------------------------------------------------------
    // URI accessor
    // -------------------------------------------------------------------------

    /**
     * Get a registered URI from the container.
     *
     * @param string $path   optional path to append (leading slash is stripped)
     * @param string $prefix URI key — see registerBaseUris() for available keys
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @since 1.0.0
     */
    public function uri(string $path = '', string $prefix = 'home'): string
    {
        $base = $this->get('uri.' . $prefix);

        return $path !== '' && $path !== '0'
            ? $base . '/' . ltrim($path, '/')
            : $base;
    }
}
