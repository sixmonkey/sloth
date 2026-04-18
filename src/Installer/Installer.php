<?php

declare(strict_types=1);

namespace Sloth\Installer;

use Composer\IO\IOInterface;
use Composer\Script\Event;
use Sloth\Utility\Utility;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * Installer class for setting up the Sloth WordPress theme.
 *
 * This class serves as the Composer script entrypoint. The two public static
 * methods (config, config_quiet) are registered in composer.json and called
 * by Composer during post-install/post-update. Internally, each call creates
 * a fresh instance so all state is encapsulated and never shared between runs.
 *
 * @since 1.0.0
 */
class Installer
{
    /**
     * Filesystem helper from symfony/filesystem.
     *
     * Composer depends on symfony/filesystem itself, so this is always
     * available without adding an explicit require to composer.json.
     *
     * @since 1.0.0
     */
    private Filesystem $fs;

    /**
     * Composer IO interface used for all terminal interaction.
     *
     * @since 1.0.0
     */
    private IOInterface $io;

    /**
     * Absolute path to the project root (where composer.json lives).
     *
     * @since 1.0.0
     */
    private string $baseDir;

    /**
     * Resolved directory paths derived from composer.json extra config.
     *
     * Keys: webroot, wp, mu, themes, app, config, cache.
     * Values: absolute paths.
     *
     * @var array<string, string>
     * @since 1.0.0
     */
    private array $dirs = [];

    /**
     * Human-readable name of the theme to be created.
     *
     * Defaults to the project root directory name and may be overridden
     * interactively during {@see dialog()}.
     *
     * @since 1.0.0
     */
    private string $themeName;

    /**
     * Author name written into style.css.
     *
     * Defaults to the OS user running Composer.
     *
     * @since 1.0.0
     */
    private string $authorName;

    /**
     * One-line description written into style.css.
     *
     * @since 1.0.0
     */
    private string $themeDescription;

    /**
     * Absolute path to the bundled default "sloth-theme" directory.
     *
     * Set during {@see gatherInfo()} and used by {@see defaultThemeExists()}
     * and {@see renameTheme()}.
     *
     * @since 1.0.0
     */
    private string $dirThemeDefault;

    /**
     * Absolute path to the renamed theme directory.
     *
     * Populated by {@see renameTheme()} after the user has confirmed the name.
     *
     * @since 1.0.0
     */
    private string $dirThemeNew;

    // -------------------------------------------------------------------------
    // Public Composer script entrypoints
    // -------------------------------------------------------------------------

    /**
     * Primary Composer script entrypoint.
     *
     * Registers in composer.json as:
     *   "post-install-cmd": "Sloth\\Installer\\Installer::config"
     *
     * When called interactively (without --no-interaction) and the default
     * theme directory is present, the user is prompted for theme name, author,
     * and description before files are set up.
     *
     * @param Event $event The Composer script event injected by Composer.
     * @since 1.0.0
     */
    public static function config(Event $event): void
    {
        new self($event)->run();
    }

    /**
     * Silent Composer script entrypoint.
     *
     * Equivalent to calling config() with --no-interaction. Kept for
     * backwards compatibility with existing composer.json configurations.
     *
     * Registers in composer.json as:
     *   "post-install-cmd": "Sloth\\Installer\\Installer::config_quiet"
     *
     * @param Event $event The Composer script event injected by Composer.
     *
     * @deprecated Use config() with the --no-interaction flag instead:
     *             composer install --no-interaction
     * @since      1.0.0
     */
    public static function config_quiet(Event $event): void
    {
        $installer = new self($event);
        $installer->io->writeError(
            '<warning>config_quiet is deprecated. Use config() with --no-interaction instead.</warning>'
        );
        $installer->run();
    }

    // -------------------------------------------------------------------------
    // Constructor & core flow
    // -------------------------------------------------------------------------

    /**
     * Initialise the installer for a single Composer run.
     *
     * Bootstraps the Filesystem helper, resolves all paths from the Composer
     * configuration, and sets sensible defaults for theme metadata.
     *
     * @param Event $event The Composer script event.
     * @since 1.0.0
     */
    private function __construct(Event $event)
    {
        $this->fs = new Filesystem();
        $this->io = $event->getIO();
        $this->gatherInfo($event);
    }

    /**
     * Execute the full setup sequence.
     *
     * If the installer is running interactively and the default theme directory
     * is present, the user is first asked for theme metadata before any files
     * are written.
     *
     * @since 1.0.0
     */
    private function run(): void
    {
        if ($this->defaultThemeExists() && $this->io->isInteractive()) {
            $this->dialog();
            $this->renameTheme();
        }

        $this->mkDirs();
        $this->rebuildIndex();
        $this->initializeSalts();
        $this->initializeDotenv();
        $this->initializeWpconfig();
        $this->initializeHtaccess();
        $this->initializePlugin();
        $this->initializeBootstrap();
    }

    // -------------------------------------------------------------------------
    // Info gathering
    // -------------------------------------------------------------------------

    /**
     * Resolve all paths and default metadata from the Composer configuration.
     *
     * Reads the `extra` section of the project's composer.json to determine:
     * - The web root (default: public/)
     * - The WordPress install directory
     * - The mu-plugins directory (via installer-paths)
     * - The themes directory (via installer-paths)
     *
     * Also sets default values for {@see $themeName} and {@see $authorName}.
     *
     * @param Event $event The Composer script event.
     * @since 1.0.0
     */
    private function gatherInfo(Event $event): void
    {
        $composer = $event->getComposer();
        $extraConfig = $composer->getPackage()->getExtra();

        $this->baseDir = Path::canonicalize(
            dirname($composer->getConfig()->getConfigSource()->getName())
        );

        $webRoot = $this->absPath($extraConfig['webroot'] ?? 'public');
        $wpInstallDir = $this->absPath($extraConfig['wordpress-install-dir'] ?? '');

        $installerPaths = $extraConfig['installer-paths'] ?? [];
        $muPluginsDir = $this->resolveInstallerPath($installerPaths, 'type:wordpress-muplugin');
        $themesDir = $this->resolveInstallerPath($installerPaths, 'type:wordpress-theme');

        $this->dirs = [
            'webroot' => $webRoot,
            'wp' => $wpInstallDir,
            'mu' => $muPluginsDir,
            'themes' => $themesDir,
            'app' => $this->absPath('app'),
            'config' => $this->absPath('app/config'),
            'cache' => $this->absPath('app/cache'),
        ];

        $this->themeName = basename($this->baseDir);
        $this->authorName = get_current_user();
        $this->themeDescription = $this->themeName . ': Just another WordPress theme.';
        $this->dirThemeDefault = Path::join($themesDir, 'sloth-theme');
    }

    /**
     * Resolve a directory from the installer-paths extra config by type condition.
     *
     * Searches the installer-paths map for the first entry whose conditions
     * include the given type string (e.g. "type:wordpress-muplugin") and
     * returns the absolute path with the `/{$name}/` placeholder stripped.
     *
     * @param array<string, mixed> $installerPaths The installer-paths map from composer.json extra.
     * @param string $type The type condition to match (e.g. "type:wordpress-theme").
     * @return string Absolute resolved path, or the project base dir if not found.
     * @since 1.0.0
     */
    private function resolveInstallerPath(array $installerPaths, string $type): string
    {
        foreach ($installerPaths as $path => $conditions) {
            if (in_array($type, (array) $conditions, strict: true)) {
                return $this->absPath(str_replace('/{$name}', '', $path));
            }
        }

        return $this->baseDir;
    }

    // -------------------------------------------------------------------------
    // Interactive dialog
    // -------------------------------------------------------------------------

    /**
     * Prompt the user for theme metadata via the Composer IO interface.
     *
     * Only called when {@see IOInterface::isInteractive()} returns true and
     * the default theme directory exists. Updates {@see $themeName},
     * {@see $authorName}, and {@see $themeDescription} in place.
     *
     * @since 1.0.0
     */
    private function dialog(): void
    {
        $this->io->write('');
        $this->io->write('<info>┌─────────────────────────────┐</info>');
        $this->io->write('<info>│   🦥  Sloth Theme Setup      │</info>');
        $this->io->write('<info>└─────────────────────────────┘</info>');
        $this->io->write('');

        $this->themeName = $this->io->ask(
            'What will your WordPress theme be called? [<comment>' . $this->themeName . '</comment>]: ',
            $this->themeName
        );

        $this->authorName = $this->io->ask(
            "What is the name of your theme's author? [<comment>" . $this->authorName . '</comment>]: ',
            $this->authorName
        );

        // Rebuild default description now that we have the final theme name.
        $this->themeDescription = $this->themeName . ': Just another WordPress theme.';
        $this->themeDescription = $this->io->ask(
            'Please describe your theme [<comment>' . $this->themeDescription . '</comment>]: ',
            $this->themeDescription
        );
    }

    // -------------------------------------------------------------------------
    // Setup steps
    // -------------------------------------------------------------------------

    /**
     * Create all required project directories if they do not already exist.
     *
     * Uses {@see Filesystem::mkdir()} which is a no-op for directories that
     * already exist, making this step safely idempotent.
     *
     * @since 1.0.0
     */
    private function mkDirs(): void
    {
        foreach ($this->dirs as $dir) {
            $this->fs->mkdir($dir, 0o755);
        }
    }

    /**
     * Write a custom index.php into the web root that points to WordPress.
     *
     * WordPress ships its own index.php which hard-codes a relative path to
     * wp-blog-header.php. When WordPress lives in a subdirectory the path
     * needs to be rewritten so that requests routed through the web root
     * still bootstrap WordPress correctly.
     *
     * @since 1.0.0
     */
    private function rebuildIndex(): void
    {
        $wpIndexPath = Path::join($this->dirs['wp'], 'index.php');
        $webIndexPath = Path::join($this->dirs['webroot'], 'index.php');

        $relativeHeader = Path::makeRelative(
            Path::join($this->dirs['wp'], 'wp-blog-header.php'),
            $this->dirs['webroot']
        );

        $original = file_get_contents($wpIndexPath);
        $custom = str_replace(
            "__DIR__ . '/wp-blog-header.php'",
            "__DIR__ . '/" . $relativeHeader . "'",
            $original
        );

        file_put_contents($webIndexPath, $custom);
    }

    /**
     * Generate WordPress authentication salt keys and write them to a PHP file.
     *
     * Fetches fresh salts from the WordPress.org API and stores them in
     * app/config/salts.php. Skipped if the file already exists so that
     * re-running the installer does not rotate keys on an existing install.
     *
     * @since 1.0.0
     */
    private function initializeSalts(): void
    {
        $saltsFile = Path::join($this->dirs['config'], 'salts.php');

        if (!$this->fs->exists($saltsFile)) {
            $salts = "<?php\n" . file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
            file_put_contents($saltsFile, $salts);
        }
    }

    /**
     * Copy .env.example to .env if no .env file exists yet.
     *
     * Allows developers to customise their local environment without
     * accidentally overwriting an existing configuration on subsequent runs.
     *
     * @since 1.0.0
     */
    private function initializeDotenv(): void
    {
        $envFile = Path::join($this->baseDir, '.env');
        $envExample = Path::join($this->baseDir, '.env.example');

        if (!$this->fs->exists($envFile) && $this->fs->exists($envExample)) {
            $this->fs->copy($envExample, $envFile);
        }
    }

    /**
     * Copy the bundled wp-config.php into the web root.
     *
     * Always overwrites to ensure the web root contains the latest version
     * of the config stub shipped with this package.
     *
     * @since 1.0.0
     */
    private function initializeWpconfig(): void
    {
        $this->fs->copy(
            Path::join(dirname(__DIR__), 'wp-config.php'),
            Path::join($this->dirs['webroot'], 'wp-config.php')
        );
    }

    /**
     * Copy the bundled .htaccess into the web root if one does not exist yet.
     *
     * Preserves any .htaccess customisations the developer may have made after
     * the initial install.
     *
     * @since 1.0.0
     */
    private function initializeHtaccess(): void
    {
        $htaccess = Path::join($this->dirs['webroot'], '.htaccess');

        if (!$this->fs->exists($htaccess)) {
            $this->fs->copy(
                Path::join(dirname(__DIR__), '.htaccess'),
                $htaccess
            );
        }
    }

    /**
     * Copy the Sloth mu-plugin into the mu-plugins directory.
     *
     * Must-use plugins are loaded automatically by WordPress without
     * activation, making this the right place for framework-level code.
     *
     * @since 1.0.0
     */
    private function initializePlugin(): void
    {
        $this->fs->copy(
            Path::join(dirname(__DIR__), 'sloth.php'),
            Path::join($this->dirs['mu'], 'sloth.php')
        );
    }

    /**
     * Copy the bootstrap file into the project root.
     *
     * @since 1.0.0
     */
    private function initializeBootstrap(): void
    {
        $this->fs->copy(
            Path::join(dirname(__DIR__), 'bootstrap.php'),
            Path::join($this->baseDir, 'bootstrap.php')
        );
    }

    // -------------------------------------------------------------------------
    // Theme helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether the bundled default "sloth-theme" directory is present.
     *
     * Used to decide whether the renaming dialog and {@see renameTheme()}
     * should run. Returns false after the theme has already been renamed.
     *
     * @return bool True if the default theme directory exists.
     * @since 1.0.0
     */
    private function defaultThemeExists(): bool
    {
        return $this->fs->exists($this->dirThemeDefault);
    }

    /**
     * Rename the default theme directory and write style.css.
     *
     * Transforms {@see $themeName} into a slug via {@see Utility::viewize()}
     * and renames the directory accordingly, then delegates to
     * {@see buildStyleCss()} to write the theme header.
     *
     * @since 1.0.0
     */
    private function renameTheme(): void
    {
        $this->dirThemeNew = Path::join(
            $this->dirs['themes'],
            Utility::viewize(strtolower($this->themeName))
        );

        $this->fs->rename($this->dirThemeDefault, $this->dirThemeNew);
        $this->buildStyleCss();
        $this->buildScreenshot();
    }

    /**
     * Write the WordPress theme header into style.css.
     *
     * WordPress reads theme metadata (name, author, version, description)
     * from the comment block at the top of style.css. This method generates
     * that block from the values collected during {@see dialog()}.
     *
     * @since 1.0.0
     */
    private function buildStyleCss(): void
    {
        $css = sprintf(
            "/*\nTheme Name: %s\nAuthor: %s\nVersion: 0.0.1\nDescription: %s\n*/",
            $this->themeName,
            $this->authorName,
            $this->themeDescription
        );

        $this->fs->dumpFile(Path::join($this->dirThemeNew, 'style.css'), $css);
    }

    // -------------------------------------------------------------------------
    // Path helper
    // -------------------------------------------------------------------------

    /**
     * Resolve a relative path against the project base directory.
     *
     * Thin wrapper around {@see Path::join()} and {@see Path::canonicalize()}
     * so callers don't need to reference $this->baseDir directly.
     *
     * @param string $relative A path relative to the project root.
     * @return string Absolute, canonicalized path.
     * @since 1.0.0
     */
    private function absPath(string $relative): string
    {
        return Path::canonicalize(Path::join($this->baseDir, $relative));
    }

    /**
     * @return void
     */
    private function buildScreenshot(): void
    {
        $template = file_get_contents(Path::join(dirname(__DIR__), 'screenshot.svg'));

        $svg = strtr($template, [
            '{{themeName}}' => htmlspecialchars($this->themeName),
            '{{authorName}}' => htmlspecialchars($this->authorName),
        ]);

        $pngPath = Path::join($this->dirThemeNew, 'screenshot.png');

        if (extension_loaded('imagick')) {
            $imagick = new \Imagick();
            $imagick->readImageBlob($svg);
            $imagick->setImageFormat('png');
            $this->fs->dumpFile($pngPath, $imagick->getImageBlob());
            $imagick->destroy();
            return;
        }

        // Fallback: SVG as-is, besser als nichts
        $this->fs->dumpFile(
            Path::join($this->dirThemeNew, 'screenshot.svg'),
            $svg
        );

        $this->io->writeError('<warning>Imagick not available – screenshot.svg created instead of screenshot.png.</warning>');
    }
}
