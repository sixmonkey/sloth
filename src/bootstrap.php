<?php
/**
 * define some useful constants for commonly used directories
 */
/*----------------------------------------------------*/
// Directory separator
/*----------------------------------------------------*/
defined( 'DS' ) ? DS : define( 'DS', DIRECTORY_SEPARATOR );
/*----------------------------------------------------*/
// Root directory
/*----------------------------------------------------*/
defined( 'DIR_ROOT' ) ? DIR_ROOT : define( 'DIR_ROOT', dirname( __FILE__ ) . DS );
/*----------------------------------------------------*/
// App directory
/*----------------------------------------------------*/
defined( 'DIR_APP' ) ? DIR_APP : define( 'DIR_APP', DIR_ROOT . 'app' . DS );
/*----------------------------------------------------*/
// Cache directory
/*----------------------------------------------------*/
defined( 'DIR_CACHE' ) ? DIR_CACHE : define( 'DIR_CACHE', DIR_APP . 'cache' . DS );
/*----------------------------------------------------*/
// Config directory
/*----------------------------------------------------*/
defined( 'DIR_CFG' ) ? DIR_CFG : define( 'DIR_CFG', DIR_APP . 'config' . DS );
/*----------------------------------------------------*/
// ENV Config directory
/*----------------------------------------------------*/
defined( 'DIR_ENVCFG' ) ? DIR_CFG : define( 'DIR_ENVCFG', DIR_CFG . 'environments' . DS );
/*----------------------------------------------------*/
// Webroot directory
/*----------------------------------------------------*/
defined( 'DIR_WWW' ) ? DIR_WWW : define( 'DIR_WWW', DIR_ROOT . 'public' . DS );
/*----------------------------------------------------*/
// WordPress directory
/*----------------------------------------------------*/
defined( 'DIR_CMS' ) ? DIR_CMS : define( 'DIR_CMS', DIR_WWW . 'cms' . DS );
/*----------------------------------------------------*/
// Plugins and MU Plugins directory
/*----------------------------------------------------*/
defined( 'DIR_EXTENSIONS' ) ? DIR_EXTENSIONS : define( 'DIR_EXTENSIONS', DIR_WWW . 'extensions' . DS );
/*----------------------------------------------------*/
// Plugins directory
/*----------------------------------------------------*/
defined( 'DIR_PLUGINS' ) ? DIR_PLUGINS : define( 'DIR_PLUGINS', DIR_EXTENSIONS . 'plugins' . DS );
/*----------------------------------------------------*/
// MU Plugins directory
/*----------------------------------------------------*/
defined( 'DIR_EXTENSIONS' ) ? DIR_EXTENSIONS : define( 'DIR_COMPONENTS', DIR_EXTENSIONS . 'components' . DS );
/*----------------------------------------------------*/
// Vendor directory
/*----------------------------------------------------*/
defined( 'DIR_VENDOR' ) ? DIR_VENDOR : define( 'DIR_VENDOR', DIR_ROOT . 'vendor' . DS );
/*----------------------------------------------------*/
// Sloth directory
/*----------------------------------------------------*/
defined( 'DIR_SLOTH' ) ? DIR_SLOTH : define( 'DIR_SLOTH', DIR_ROOT . 'sloth' . DS );

/**
 * Include composer autoload
 */

$loader = require_once( DIR_VENDOR . DS . 'autoload.php' );

/**
 * add autoload directories
 */
$loader->addPsr4( 'App\\Model\\', DIR_APP . 'Model' );
$loader->addPsr4( 'App\\Traits\\', DIR_APP . 'Traits' );
$loader->addPsr4( 'App\\Model\\', DIR_APP . 'Model' );


/**
 * Use Dotenv to set required environment variables and load .env file in root
 */
if ( file_exists( DIR_ROOT . '.env' ) ) {
    $dotenv = new Dotenv\Dotenv( DIR_ROOT );
    $dotenv->load();
    $dotenv->required( [ 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WP_HOME', 'WP_SITEURL' ] );
}

/**
 * Shorthand for Configure in env configs
 */
class_alias( '\Sloth\Configure\Configure', 'Configure' );

/**
 * env config
 */
# get current environment
if ( getenv( 'WP_ENV' ) !== false ) {
    define( 'WP_ENV', getenv( 'WP_ENV' ) );
} else if ( file_exists( DIR_ENVCFG . $_SERVER['HTTP_HOST'] . '.config.php' ) ) {
    define( 'WP_ENV', $_SERVER['HTTP_HOST'] );
} else if ( file_exists( DIR_ENVCFG . '/config/qundg-config.' . gethostname() . '.config.php' ) ) {
    define( 'WP_ENV', gethostname() );
} else {
    define( 'WP_ENV', 'production' );
}

$env_config = DIR_CFG . 'environments' . DS . WP_ENV . '.config.php';
if ( file_exists( $env_config ) ) {
    require_once DIR_CFG . 'environments' . DS . WP_ENV . '.config.php';
}

/**
 * app config
 */
$app_config = DIR_CFG . 'app.config.php';
if ( file_exists( $app_config ) ) {
    require_once DIR_CFG . 'app.config.php';
}

/**
 * Make sure WP_DEBUG is defined
 */
defined( 'WP_DEBUG' ) ? WP_DEBUG : define( 'WP_DEBUG', false );
/**
 * URLs
 */
defined( 'WP_HOME' ) ? DS : define( 'WP_HOME', getenv( 'WP_HOME' ) );
defined( 'WP_SITEURL' ) ? DS : define( 'WP_SITEURL', getenv( 'WP_SITEURL' ) );

/**
 * WP custom path
 */
defined( 'WP_PATH' ) ? WP_PATH : define( 'WP_PATH', substr( WP_SITEURL, strrpos( WP_SITEURL, '/' ) ) );

/**
 * DB settings
 */
defined( 'DB_NAME' ) ? DB_NAME : define( 'DB_NAME', getenv( 'DB_NAME' ) );
defined( 'DB_USER' ) ? DB_USER : define( 'DB_USER', getenv( 'DB_USER' ) );
defined( 'DB_PASSWORD' ) ? DB_PASSWORD : define( 'DB_PASSWORD', getenv( 'DB_PASSWORD' ) );
defined( 'DB_HOST' ) ? DB_HOST : define( 'DB_HOST', getenv( 'DB_HOST' ) ?: 'localhost' );
defined( 'DB_CHARSET' ) ? DB_CHARSET : define( 'DB_CHARSET', 'utf8mb4' );
defined( 'DB_COLLATE' ) ? DB_COLLATE : define( 'DB_COLLATE', '' );
$table_prefix = getenv( 'DB_PREFIX' ) ?: 'wp_';
defined( 'DB_PREFIX' ) ? DB_PREFIX : define( 'DB_PREFIX', $table_prefix );

/*
 * Custom Settings
 */
defined( 'AUTOMATIC_UPDATER_DISABLED' ) ? AUTOMATIC_UPDATER_DISABLED : define( 'AUTOMATIC_UPDATER_DISABLED', true );
defined( 'DISABLE_WP_CRON' ) ? DISABLE_WP_CRON : define( 'DISABLE_WP_CRON', getenv( 'DISABLE_WP_CRON' ) ?: false );
defined( 'DISALLOW_FILE_EDIT' ) ? DISALLOW_FILE_EDIT : define( 'DISALLOW_FILE_EDIT', true );

/**
 * Bootstrap WordPress
 */
defined( 'ABSPATH' ) ? ABSPATH : define( 'ABSPATH', realpath( DIR_WWW . DS . WP_PATH ) . DS );

/**
 * Custom Media, Plugins and Theme paths
 *
 * @see https://gist.github.com/tzkmx/4c832432bc63fd67a3a16f940a184145
 */
define( 'WP_CONTENT_DIR', DIR_WWW );
define( 'WP_CONTENT_URL', WP_HOME );
define( 'WP_PLUGIN_DIR', DIR_WWW . 'extensions' . DS . 'plugins' );
define( 'WP_PLUGIN_URL', WP_HOME . '/extensions/plugins' );
define( 'WPMU_PLUGIN_DIR', DIR_WWW . 'extensions' . DS . 'components' );
define( 'WPMU_PLUGIN_URL', WP_HOME . '/extensions/components' );


use Sloth\Core\Sloth;

/*
 * Globally register the instance.
 */
$GLOBALS['sloth'] = Sloth::getInstance();
