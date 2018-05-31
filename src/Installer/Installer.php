<?php

namespace Sloth\Installer;

use Composer\Script\Event;


class Installer {
	static $http_dir;
	static $base_dir;
	static $theme_name;
	static $dirs_required = [
		[ 'app' ],
		[ 'app', 'config' ],
		[ 'app', 'cache' ],
	];

	public static function config( Event $event ) {
		$vendor_dir       = dirname( $event->getComposer()->getConfig()->get( 'vendor-dir' ) );
		self::$base_dir   = $vendor_dir;
		self::$http_dir   = self::mkPath( [ self::$base_dir, 'public' ] );
		self::$theme_name = basename( $vendor_dir );

		self::mkDirs();

		self::rebuildIndex();
		self::initializeSalts();
		self::initializeDotenv();
		self::initializeWpconfig();
		self::initializeHtaccess();
		self::initializePlugin();
		self::initializeBootstrap();
		self::renameTheme();
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

		if ( ! file_exists( $dotenvToCreate ) ) {
			copy( self::mkPath( [ self::$base_dir, '.env.example' ] ), $dotenvToCreate );
			echo "Customize your .env file for required environment: $dotenvToCreate \n";
		}
	}

	protected static function initializeWpconfig() {
		copy( self::mkPath( [ dirname( __DIR__ ), 'wp-config.php' ] ),
			self::mkPath( [ self::$http_dir, 'wp-config.php' ] ) );
	}

	protected static function initializePlugin() {
		$dir_components = self::mkPath( [ self::$http_dir, 'extensions', 'components' ] );
		if ( ! is_dir( $dir_components ) ) {
			mkdir( $dir_components, 0755 );
		}
		copy( self::mkPath( [ dirname( __DIR__ ), 'sloth.php' ] ),
			self::mkPath( [ $dir_components, 'sloth.php' ] ) );
	}

	protected static function initializeBootstrap() {
		copy( self::mkPath( [ dirname( __DIR__ ), 'bootstrap.php' ] ),
			self::mkPath( [ self::$base_dir, 'bootstrap.php' ] ) );
	}

	protected static function initializeHtaccess() {
		copy( self::mkPath( [ dirname( __DIR__ ), '.htaccess' ] ),
			self::mkPath( [ self::$http_dir, '.htaccess' ] ) );
	}

	public static function renameTheme() {
		$dir_theme_default = self::mkPath( [ self::$http_dir, 'themes', 'sloth-theme' ] );
		$dir_theme_new     = self::mkPath( [ self::$http_dir, 'themes', self::$theme_name ] );
		if ( is_dir( $dir_theme_default ) ) {
			rename( $dir_theme_default, $dir_theme_new );
		}
	}

	public static function mkPath( $parts ) {
		return implode( DIRECTORY_SEPARATOR, $parts );
	}
}
