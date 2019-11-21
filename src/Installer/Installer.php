<?php

namespace Sloth\Installer;

use Composer\Script\Event;
use League\CLImate\CLImate;
use Sloth\Utility\Utility;


class Installer {
    static $http_dir;
    static $base_dir;
    static $theme_name;
    static $dirs_required = [
        [ 'app' ],
        [ 'app', 'config' ],
        [ 'app', 'cache' ],
    ];
    static $dir_theme_new;
    static $authorname;
    static $themedescription;

    public static function config( Event $event ) {
        $vendor_dir       = dirname( $event->getComposer()->getConfig()->get( 'vendor-dir' ) );
        self::$base_dir   = $vendor_dir;
        self::$http_dir   = self::mkPath( [ self::$base_dir, 'public' ] );
        self::$theme_name = basename( $vendor_dir );
        self::$authorname = get_current_user();

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

    public static function config_quiet( Event $event ) {
        $vendor_dir     = dirname( $event->getComposer()->getConfig()->get( 'vendor-dir' ) );
        self::$base_dir = $vendor_dir;
        self::$http_dir = self::mkPath( [ self::$base_dir, 'public' ] );

        self::mkDirs();

        self::rebuildIndex();
        self::initializeSalts();
        self::initializeDotenv();
        self::initializeWpconfig();
        self::initializeHtaccess();
        self::initializePlugin();
        self::initializeBootstrap();
    }

    protected static function mkDirs() {
        foreach ( self::$dirs_required as $dir ) {
            array_unshift( $dir, self::$base_dir );
            $dir = self::mkPath( $dir );
            if ( ! is_dir( $dir ) ) {
                mkdir( $dir, 0755 );
            }
        }

    }

    protected static function rebuildIndex() {
        $custom_index_wp_path = self::mkPath( [ self::$http_dir, 'index.php' ] );

        if ( ! file_exists( $custom_index_wp_path ) ) {
            $original_index_wp_path = self::mkPath( [ self::$http_dir, 'cms', 'index.php' ] );
            $original_index         = file_get_contents( $original_index_wp_path );

            $custom_index = str_replace( "'/wp-blog-header.php'", "'/cms/wp-blog-header.php'", $original_index );

            file_put_contents( $custom_index_wp_path, $custom_index );
        }
    }

    protected static function initializeSalts() {
        $salts_filename = self::mkPath( [ self::$base_dir, 'app', 'config', 'salts.php' ] );
        if ( ! file_exists( $salts_filename ) ) {
            $salts = "<?php\n" . file_get_contents( 'https://api.wordpress.org/secret-key/1.1/salt/' );
            file_put_contents( $salts_filename, $salts );
        }
    }

    protected static function initializeDotenv() {
        $dotenvToCreate = self::mkPath( [ self::$base_dir, '.env' ] );
        $dotEnvSrc      = self::mkPath( [ self::$base_dir, '.env.example' ] );

        if ( ! file_exists( $dotenvToCreate ) ) {
            if ( file_exists( $dotEnvSrc ) ) {
                copy( $dotEnvSrc, $dotenvToCreate );
            }
        }
    }

    protected static function initializeWpconfig() {
        $wpConfigToCreate = self::mkPath( [ self::$http_dir, 'wp-config.php' ] );

        if ( ! file_exists( $wpConfigToCreate ) ) {
            copy( self::mkPath( [ dirname( __DIR__ ), 'wp-config.php' ] ),
                $wpConfigToCreate );
        }
    }

    protected static function initializePlugin() {
        $dir_components = self::mkPath( [ self::$http_dir, 'extensions', 'components' ] );
        if ( ! is_dir( $dir_components ) ) {
            mkdir( $dir_components, 0755 );
        }
        copy( self::mkPath( [ dirname( __DIR__ ), 'sloth.php' ] ),
            self::mkPath( [ $dir_components, 'sloth.php' ] ) );
    }

    protected static function addCLI() {
        copy( self::mkPath( [ dirname( __DIR__ ), 'sloth-cli.php' ] ),
            self::mkPath( [ self::$base_dir, 'sloth.php' ] ) );
    }

    protected static function initializeBootstrap() {
        copy( self::mkPath( [ dirname( __DIR__ ), 'bootstrap.php' ] ),
            self::mkPath( [ self::$base_dir, 'bootstrap.php' ] ) );
    }

    protected static function initializeHtaccess() {
        $htaccessFile = self::mkPath( [ self::$http_dir, '.htaccess' ] );
        if ( ! file_exists( $htaccessFile ) ) {
            copy( self::mkPath( [ dirname( __DIR__ ), '.htaccess' ] ),
                $htaccessFile );
        }
    }

    public static function dialog() {
        $climate = new CLImate;
        $data    = [];

        // Set themename
        $input = $climate->input( 'What will your WordPress-theme be called? [' . self::$theme_name . ']' );
        $input->defaultTo( self::$theme_name );
        self::$theme_name = $input->prompt();

        // Set authorname
        $input = $climate->input( 'What is the name of your theme\'s author? [' . self::$authorname . ']' );
        $input->defaultTo( self::$authorname );
        self::$authorname = $input->prompt();

        // Set description
        self::$themedescription = self::$theme_name . ": Just another WordPress theme.";
        $input                  = $climate->input( 'Please describe your theme [' . self::$themedescription . ']' );
        $input->defaultTo( self::$themedescription );
        self::$themedescription = $input->prompt();
    }

    public static function buildStyleCss() {
        @file_put_contents( self::$dir_theme_new . DIRECTORY_SEPARATOR . 'style.css',
            "/*\nTheme Name: " . self::$theme_name . "\nAuthor: " . self::$authorname . "\nVersion: 0.0.1\nDescription: " . self::$themedescription . "\n*/" );
    }

    public static function renameTheme() {
        $dir_theme_default   = self::mkPath( [ self::$http_dir, 'themes', 'sloth-theme' ] );
        self::$dir_theme_new = self::mkPath( [
            self::$http_dir,
            'themes',
            Utility::viewize( strtolower( self::$theme_name ) ),
        ] );
        if ( is_dir( $dir_theme_default ) ) {
            rename( $dir_theme_default, self::$dir_theme_new );
            self::buildStyleCss();
        }
    }

    public static function mkPath( $parts ) {
        return implode( DIRECTORY_SEPARATOR, $parts );
    }
}
