<?php

/*
 * bootstrap.php
 *
 * WordPress environment configuration.
 *
 * This file is the only entry point that must exist before WordPress loads.
 * It is responsible for exactly three things:
 *
 *   1. Loading the Composer autoloader
 *   2. Loading environment variables from .env
 *   3. Defining WordPress constants (DB, URLs, paths)
 *
 * Everything else — framework boot, service providers, Corcel, Twig — is
 * handled by Sloth itself on the `after_setup_theme` hook.
 *
 * ## What does NOT belong here
 *
 * - Sloth\Core\Application or any framework class instantiation
 * - Configure::boot() or any facade usage
 * - DIR_* constants (deprecated — use app()->path() instead)
 * - ServiceProvider registration
 *
 * ## Deprecation notice
 *
 * The DIR_* constants below are kept for backwards compatibility with themes
 * that reference them directly. They will be removed in a future major version.
 * Use app()->path('app'), app()->path('cache') etc. instead.
 */

// -------------------------------------------------------------------------
// Autoloader
// -------------------------------------------------------------------------

require_once __DIR__ . '/vendor/autoload.php';

// -------------------------------------------------------------------------
// Configure — must be available before WordPress loads
//
// Theme includes (e.g. nav-menus.php) may reference Configure before
// after_setup_theme fires. The class_alias allows themes to use
// Configure:: without the full namespace.
//
// TODO: move into ConfigServiceProvider once Step 6 is complete.
// -------------------------------------------------------------------------

class_alias(\Sloth\Configure\Configure::class, 'Configure');
\Sloth\Configure\Configure::boot();

// -------------------------------------------------------------------------
// Environment variables
// -------------------------------------------------------------------------

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'WP_HOME', 'WP_SITEURL']);
}

// -------------------------------------------------------------------------
// WordPress URLs
// -------------------------------------------------------------------------

defined('WP_HOME')    || define('WP_HOME',    env('WP_HOME'));
defined('WP_SITEURL') || define('WP_SITEURL', env('WP_SITEURL'));

// -------------------------------------------------------------------------
// WordPress database
// -------------------------------------------------------------------------

defined('DB_NAME')     || define('DB_NAME',     env('DB_NAME'));
defined('DB_USER')     || define('DB_USER',     env('DB_USER'));
defined('DB_PASSWORD') || define('DB_PASSWORD', env('DB_PASSWORD'));
defined('DB_HOST')     || define('DB_HOST',     env('DB_HOST', 'localhost'));
defined('DB_CHARSET')  || define('DB_CHARSET',  'utf8mb4');
defined('DB_COLLATE')  || define('DB_COLLATE',  '');
defined('DB_PREFIX')   || define('DB_PREFIX',   env('DB_PREFIX', 'wp_'));

$table_prefix = DB_PREFIX;

// -------------------------------------------------------------------------
// WordPress debug
// -------------------------------------------------------------------------

defined('WP_DEBUG') || define('WP_DEBUG', (bool) env('WP_DEBUG', false));

// -------------------------------------------------------------------------
// WordPress paths
// -------------------------------------------------------------------------

$webroot = __DIR__ . '/public';
$wpPath  = substr(WP_SITEURL, strrpos(WP_SITEURL, '/'));

defined('ABSPATH') || define('ABSPATH', realpath($webroot . $wpPath) . '/');

define('WP_CONTENT_DIR',  $webroot);
define('WP_CONTENT_URL',  env('WP_CONTENT_URL', WP_HOME));
define('WP_PLUGIN_DIR',   $webroot . '/extensions/plugins');
define('WP_PLUGIN_URL',   WP_HOME . '/extensions/plugins');
define('WPMU_PLUGIN_DIR', $webroot . '/extensions/components');
define('WPMU_PLUGIN_URL', WP_HOME . '/extensions/components');

// -------------------------------------------------------------------------
// WordPress settings
// -------------------------------------------------------------------------

defined('AUTOMATIC_UPDATER_DISABLED') || define('AUTOMATIC_UPDATER_DISABLED', true);
defined('DISABLE_WP_CRON')            || define('DISABLE_WP_CRON', (bool) env('DISABLE_WP_CRON', false));
defined('DISALLOW_FILE_EDIT')         || define('DISALLOW_FILE_EDIT', true);

// -------------------------------------------------------------------------
// Deprecated DIR_* constants
//
// Kept for backwards compatibility with themes that reference these directly.
// Use app()->path('app'), app()->path('cache') etc. instead.
// Will be removed in a future major version — see MIGRATE.md.
// -------------------------------------------------------------------------

defined('DS')          || define('DS',          DIRECTORY_SEPARATOR);
defined('DIR_ROOT')    || define('DIR_ROOT',     __DIR__ . DS);
defined('DIR_APP')     || define('DIR_APP',      DIR_ROOT . 'app'    . DS);
defined('DIR_CACHE')   || define('DIR_CACHE',    DIR_APP  . 'cache'  . DS);
defined('DIR_CFG')     || define('DIR_CFG',      DIR_APP  . 'config' . DS);
defined('DIR_ENVCFG')  || define('DIR_ENVCFG',   DIR_CFG  . 'environments' . DS);
defined('DIR_WWW')     || define('DIR_WWW',      DIR_ROOT . 'public' . DS);
defined('DIR_CMS')     || define('DIR_CMS',      DIR_WWW  . 'cms'    . DS);
defined('DIR_VENDOR')  || define('DIR_VENDOR',   DIR_ROOT . 'vendor' . DS);
defined('DIR_PLUGINS') || define('DIR_PLUGINS',  DIR_WWW  . 'extensions' . DS . 'plugins'    . DS);
defined('DIR_SLOTH')   || define('DIR_SLOTH',    DIR_ROOT . 'sloth'  . DS);
