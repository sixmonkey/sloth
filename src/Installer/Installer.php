<?php

declare(strict_types=1);

namespace Sloth\Installer;

use Composer\Script\Event;
use League\CLImate\CLImate;
use Sloth\Utility\Utility;

/**
 * Installer class for setting up the Sloth WordPress theme.
 *
 * @since 1.0.0
 */
class Installer
{
    /**
     * HTTP directory path.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $httpDir = null;

    /**
     * Base directory path.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $baseDir = null;

    /**
     * Theme name.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $themeName = null;

    /**
     * Required directories to create.
     *
     * @since 1.0.0
     * @var array<array<string>>
     */
    public static array $dirsRequired = [
        ['app'],
        ['app', 'config'],
        ['app', 'cache'],
    ];

    /**
     * New theme directory path.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $dirThemeNew = null;

    /**
     * Author name.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $authorname = null;

    /**
     * Theme description.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static ?string $themedescription = null;

    /**
     * Run the configuration process with interactive prompts.
     *
     * @since 1.0.0
     *
     * @param Event $event The Composer event
     *
     * @return void
     */
    public static function config(Event $event): void
    {
        $vendorDir = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
        self::$baseDir = $vendorDir;
        self::$httpDir = self::mkPath([self::$baseDir, 'public']);
        self::$themeName = basename($vendorDir);
        self::$authorname = (string) get_current_user();

        self::dialog();

        self::mkDirs();

        self::rebuildIndex();
        self::initializeSalts();
        self::initializeDotenv();
        self::initializeWpconfig();
        self::initializeHtaccess();
        self::initializePlugin();
        self::addCLI();
        self::initializeBootstrap();
        self::renameTheme();
    }

    /**
     * Run the configuration process silently.
     *
     * @since 1.0.0
     *
     * @param Event $event The Composer event
     *
     * @return void
     */
    public static function config_quiet(Event $event): void
    {
        $vendorDir = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
        self::$baseDir = $vendorDir;
        self::$httpDir = self::mkPath([self::$baseDir, 'public']);

        self::mkDirs();

        self::rebuildIndex();
        self::initializeSalts();
        self::initializeDotenv();
        self::initializeWpconfig();
        self::initializeHtaccess();
        self::initializePlugin();
        self::initializeBootstrap();
    }

    /**
     * Create required directories.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function mkDirs(): void
    {
        foreach (self::$dirsRequired as $dir) {
            array_unshift($dir, (string) self::$baseDir);
            $dir = self::mkPath($dir);
            if (!is_dir($dir)) {
                mkdir($dir, 0o755);
            }
        }
    }

    /**
     * Rebuild the index.php file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function rebuildIndex(): void
    {
        $customIndexWpPath = self::mkPath([(string) self::$httpDir, 'index.php']);

        if (!file_exists($customIndexWpPath)) {
            $originalIndexWpPath = self::mkPath([(string) self::$httpDir, 'cms', 'index.php']);
            $originalIndex = file_get_contents($originalIndexWpPath);

            $customIndex = str_replace("'/wp-blog-header.php'", "'/cms/wp-blog-header.php'", $originalIndex);

            file_put_contents($customIndexWpPath, $customIndex);
        }
    }

    /**
     * Initialize WordPress salt keys.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializeSalts(): void
    {
        $saltsFilename = self::mkPath([(string) self::$baseDir, 'app', 'config', 'salts.php']);
        if (!file_exists($saltsFilename)) {
            $salts = "<?php\n" . (string) file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
            file_put_contents($saltsFilename, $salts);
        }
    }

    /**
     * Initialize .env file from example.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializeDotenv(): void
    {
        $dotenvToCreate = self::mkPath([(string) self::$baseDir, '.env']);
        $dotEnvSrc = self::mkPath([(string) self::$baseDir, '.env.example']);

        if (!file_exists($dotenvToCreate)) {
            if (file_exists($dotEnvSrc)) {
                copy($dotEnvSrc, $dotenvToCreate);
            }
        }
    }

    /**
     * Initialize wp-config.php file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializeWpconfig(): void
    {
        $wpConfigToCreate = self::mkPath([(string) self::$httpDir, 'wp-config.php']);

        if (!file_exists($wpConfigToCreate)) {
            copy(
                self::mkPath([dirname(__DIR__), 'wp-config.php']),
                $wpConfigToCreate
            );
        }
    }

    /**
     * Initialize the Sloth plugin.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializePlugin(): void
    {
        $dirComponents = self::mkPath([(string) self::$httpDir, 'extensions', 'components']);
        if (!is_dir($dirComponents)) {
            mkdir($dirComponents, 0o755);
        }
        copy(
            self::mkPath([dirname(__DIR__), 'sloth.php']),
            self::mkPath([$dirComponents, 'sloth.php'])
        );
    }

    /**
     * Add the CLI script.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function addCLI(): void
    {
        copy(
            self::mkPath([dirname(__DIR__), 'sloth-cli.php']),
            self::mkPath([(string) self::$baseDir, 'sloth.php'])
        );
    }

    /**
     * Initialize the bootstrap file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializeBootstrap(): void
    {
        copy(
            self::mkPath([dirname(__DIR__), 'bootstrap.php']),
            self::mkPath([(string) self::$baseDir, 'bootstrap.php'])
        );
    }

    /**
     * Initialize the .htaccess file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    protected static function initializeHtaccess(): void
    {
        $htaccessFile = self::mkPath([(string) self::$httpDir, '.htaccess']);
        if (!file_exists($htaccessFile)) {
            copy(
                self::mkPath([dirname(__DIR__), '.htaccess']),
                $htaccessFile
            );
        }
    }

    /**
     * Run the interactive dialog.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function dialog(): void
    {
        $climate = new CLImate();

        $input = $climate->input('What will your WordPress-theme be called? [' . self::$themeName . ']');
        $input->defaultTo((string) self::$themeName);
        self::$themeName = $input->prompt();

        $input = $climate->input("What is the name of your theme's author? [" . self::$authorname . ']');
        $input->defaultTo((string) self::$authorname);
        self::$authorname = $input->prompt();

        self::$themedescription = self::$themeName . ": Just another WordPress theme.";
        $input = $climate->input('Please describe your theme [' . self::$themedescription . ']');
        $input->defaultTo((string) self::$themedescription);
        self::$themedescription = $input->prompt();
    }

    /**
     * Build the style.css file.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function buildStyleCss(): void
    {
        $css = sprintf(
            "/*\nTheme Name: %s\nAuthor: %s\nVersion: 0.0.1\nDescription: %s\n*/",
            self::$themeName,
            self::$authorname,
            self::$themedescription
        );
        @file_put_contents(self::$dirThemeNew . DIRECTORY_SEPARATOR . 'style.css', $css);
    }

    /**
     * Rename the default theme to the configured name.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public static function renameTheme(): void
    {
        $dirThemeDefault = self::mkPath([(string) self::$httpDir, 'themes', 'sloth-theme']);
        self::$dirThemeNew = self::mkPath([
            (string) self::$httpDir,
            'themes',
            Utility::viewize(strtolower((string) self::$themeName)),
        ]);

        if (is_dir($dirThemeDefault)) {
            rename($dirThemeDefault, self::$dirThemeNew);
            self::buildStyleCss();
        }
    }

    /**
     * Join path parts with the directory separator.
     *
     * @since 1.0.0
     *
     * @param array<string> $parts Path parts to join
     *
     * @return string
     */
    public static function mkPath(array $parts): string
    {
        return implode(DIRECTORY_SEPARATOR, $parts);
    }
}
