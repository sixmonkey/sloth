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
     * @since 1.0.0
     */
    private string $themeName;

    /**
     * Author name written into style.css.
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
     * @since 1.0.0
     */
    private string $dirThemeDefault;

    /**
     * Absolute path to the renamed theme directory.
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
     * @param Event $event The Composer script event injected by Composer.
     * @deprecated Use config() with the --no-interaction flag instead.
     * @since 1.0.0
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
     * @param Event $event The Composer script event.
     * @since 1.0.0
     */
    private function gatherInfo(Event $event): void
    {
        $composer    = $event->getComposer();
        $extraConfig = $composer->getPackage()->getExtra();

        $this->baseDir = Path::canonicalize(
            dirname($composer->getConfig()->getConfigSource()->getName())
        );

        $webRoot      = $this->absPath($extraConfig['webroot'] ?? 'public');
        $wpInstallDir = $this->absPath($extraConfig['wordpress-install-dir'] ?? '');

        $installerPaths = $extraConfig['installer-paths'] ?? [];
        $muPluginsDir   = $this->resolveInstallerPath($installerPaths, 'type:wordpress-muplugin');
        $themesDir      = $this->resolveInstallerPath($installerPaths, 'type:wordpress-theme');

        $this->dirs = [
            'webroot' => $webRoot,
            'wp'      => $wpInstallDir,
            'mu'      => $muPluginsDir,
            'themes'  => $themesDir,
            'app'     => $this->absPath('app'),
            'config'  => $this->absPath('app/config'),
            'cache'   => $this->absPath('app/cache'),
        ];

        $this->themeName        = basename($this->baseDir);
        $this->authorName       = get_current_user();
        $this->themeDescription = $this->themeName . ': Just another WordPress theme.';
        $this->dirThemeDefault  = Path::join($themesDir, 'sloth-theme');
    }

    /**
     * Resolve a directory from the installer-paths extra config by type condition.
     *
     * @param array<string, mixed> $installerPaths The installer-paths map.
     * @param string               $type           The type condition to match.
     * @return string Absolute resolved path.
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
     * Prompt the user for theme metadata.
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
     * Create all required project directories.
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
     * @since 1.0.0
     */
    private function rebuildIndex(): void
    {
        $wpIndexPath  = Path::join($this->dirs['wp'], 'index.php');
        $webIndexPath = Path::join($this->dirs['webroot'], 'index.php');

        $relativeHeader = Path::makeRelative(
            Path::join($this->dirs['wp'], 'wp-blog-header.php'),
            $this->dirs['webroot']
        );

        $original = file_get_contents($wpIndexPath);
        $custom   = str_replace(
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
        $envFile    = Path::join($this->baseDir, '.env');
        $envExample = Path::join($this->baseDir, '.env.example');

        if (!$this->fs->exists($envFile) && $this->fs->exists($envExample)) {
            $this->fs->copy($envExample, $envFile);
        }
    }

    /**
     * Copy the bundled wp-config.php into the web root.
     *
     * Always overwrites to ensure the web root contains the latest version.
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
     * Always overwrites to ensure themes get the latest bootstrap version.
     *
     * @since 1.0.0
     */
    private function initializePlugin(): void
    {
        $this->fs->copy(
            Path::join(dirname(__DIR__), 'sloth.php'),
            Path::join($this->dirs['mu'], 'sloth.php'),
            true
        );
    }

    /**
     * Copy the bootstrap file into the project root.
     *
     * If bootstrap.php already exists, the user is warned and must confirm
     * before it is overwritten — any local changes will be lost.
     *
     * In non-interactive mode (e.g. CI), the file is always overwritten
     * without prompting.
     *
     * @since 1.0.0
     */
    private function initializeBootstrap(): void
    {
        $target = Path::join($this->baseDir, 'bootstrap.php');
        $source = Path::join(dirname(__DIR__), 'bootstrap.php');

        if ($this->fs->exists($target) && $this->io->isInteractive()) {
            $this->io->write('');
            $this->io->write('<warning>⚠️  Warning! Sloth will overwrite bootstrap.php!</warning>');
            $this->io->write('<warning>   Any local changes you made to this file will be lost.</warning>');
            $this->io->write('');

            $confirmed = $this->io->askConfirmation(
                '   Did you make a backup? [<comment>y/N</comment>]: ',
                false
            );

            if (!$confirmed) {
                $this->io->write('');
                $this->io->write('<info>Skipping bootstrap.php — please make a backup and run composer install again.</info>');
                $this->io->write('');
                return;
            }
        }

        $this->fs->copy($source, $target, true);
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

    /**
     * Generate a screenshot for the theme.
     *
     * Uses Imagick if available to convert SVG to PNG.
     * Falls back to writing an SVG file if Imagick is not installed.
     *
     * @since 1.0.0
     */
    private function buildScreenshot(): void
    {
        $template = file_get_contents(Path::join(dirname(__DIR__), 'screenshot.svg'));

        $svg = strtr($template, [
            '{{themeName}}'  => htmlspecialchars($this->themeName),
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

        $this->fs->dumpFile(
            Path::join($this->dirThemeNew, 'screenshot.svg'),
            $svg
        );

        $this->io->writeError('<warning>Imagick not available — screenshot.svg created instead of screenshot.png.</warning>');
    }

    // -------------------------------------------------------------------------
    // Path helper
    // -------------------------------------------------------------------------

    /**
     * Resolve a relative path against the project base directory.
     *
     * @param string $relative A path relative to the project root.
     * @return string Absolute, canonicalized path.
     * @since 1.0.0
     */
    private function absPath(string $relative): string
    {
        return Path::canonicalize(Path::join($this->baseDir, $relative));
    }
}
