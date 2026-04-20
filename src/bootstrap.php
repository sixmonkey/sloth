<?php

/*
 * bootstrap.php
 *
 * WordPress environment configuration.
 *
 * This file is owned by the project — not by Sloth. It is copied once
 * by the Sloth installer and then maintained by the project team.
 *
 * ## Responsibilities
 *
 * 1. Load the Composer autoloader
 * 2. Load environment variables from .env
 * 3. Define WordPress constants (DB, URLs, paths, salts)
 *
 * Everything else — framework boot, service providers, Corcel, Twig — is
 * handled by Sloth on the `after_setup_theme` hook (priority 0).
 *
 * ## What does NOT belong here
 *
 * - Any Sloth class instantiation
 * - Configure::boot() or facade usage
 * - ServiceProvider registration
 *
 * ## Deprecated
 *
 * The DIR_* constants are kept as a compatibility layer for themes that
 * reference them directly. Use app()->path() instead. They will be removed
 * in a future major version — see MIGRATE.md.
 */

// -------------------------------------------------------------------------
// Autoloader
// -------------------------------------------------------------------------

require_once __DIR__ . '/vendor/autoload.php';

// -------------------------------------------------------------------------
// Configure — must be available before WordPress loads
//
// Theme includes may reference Configure before after_setup_theme fires.
// TODO: remove when all themes have migrated to config() — see MIGRATE.md.
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
// WordPress salts
//
// Salts are derived from APP_SECRET if not explicitly set in .env.
// This means you only need to set one value instead of eight.
//
// For production, we recommend setting all eight explicitly using:
//   composer require rbdwllr/wordpress-salts-generator --dev
//   vendor/bin/wpsalts dotenv >> .env
//
// See MIGRATE.md if you are upgrading from the legacy salts.php approach.
// -------------------------------------------------------------------------

$_salt_secret = env('APP_SECRET', 'changeme-' . gethostname());

defined('AUTH_KEY')         || define('AUTH_KEY',         env('AUTH_KEY',         hash('sha256', $_salt_secret . 'AUTH_KEY')));
defined('SECURE_AUTH_KEY')  || define('SECURE_AUTH_KEY',  env('SECURE_AUTH_KEY',  hash('sha256', $_salt_secret . 'SECURE_AUTH_KEY')));
defined('LOGGED_IN_KEY')    || define('LOGGED_IN_KEY',    env('LOGGED_IN_KEY',    hash('sha256', $_salt_secret . 'LOGGED_IN_KEY')));
defined('NONCE_KEY')        || define('NONCE_KEY',        env('NONCE_KEY',        hash('sha256', $_salt_secret . 'NONCE_KEY')));
defined('AUTH_SALT')        || define('AUTH_SALT',        env('AUTH_SALT',        hash('sha256', $_salt_secret . 'AUTH_SALT')));
defined('SECURE_AUTH_SALT') || define('SECURE_AUTH_SALT', env('SECURE_AUTH_SALT', hash('sha256', $_salt_secret . 'SECURE_AUTH_SALT')));
defined('LOGGED_IN_SALT')   || define('LOGGED_IN_SALT',   env('LOGGED_IN_SALT',   hash('sha256', $_salt_secret . 'LOGGED_IN_SALT')));
defined('NONCE_SALT')       || define('NONCE_SALT',       env('NONCE_SALT',       hash('sha256', $_salt_secret . 'NONCE_SALT')));

unset($_salt_secret);

// -------------------------------------------------------------------------
// WordPress debug
// -------------------------------------------------------------------------

defined('WP_ENV')            || define('WP_ENV',            env('WP_ENV', 'production'));
defined('WP_DEBUG')          || define('WP_DEBUG',          (bool) env('WP_DEBUG', false));
defined('WP_DEBUG_LOG')      || define('WP_DEBUG_LOG',      (bool) env('WP_DEBUG_LOG', false));
defined('WP_DEBUG_DISPLAY')  || define('WP_DEBUG_DISPLAY',  (bool) env('WP_DEBUG_DISPLAY', false));
defined('SCRIPT_DEBUG')      || define('SCRIPT_DEBUG',      (bool) env('SCRIPT_DEBUG', false));

// -------------------------------------------------------------------------
// WordPress settings
// -------------------------------------------------------------------------

defined('WP_POST_REVISIONS')          || define('WP_POST_REVISIONS',          (int)  env('WP_POST_REVISIONS', 5));
defined('AUTOMATIC_UPDATER_DISABLED') || define('AUTOMATIC_UPDATER_DISABLED', true);
defined('DISABLE_WP_CRON')            || define('DISABLE_WP_CRON',            (bool) env('DISABLE_WP_CRON', false));
defined('DISALLOW_FILE_EDIT')         || define('DISALLOW_FILE_EDIT',          true);

// -------------------------------------------------------------------------
// WordPress paths
// -------------------------------------------------------------------------

$_webroot = __DIR__ . '/public';
$_wp_path = substr(WP_SITEURL, strrpos(WP_SITEURL, '/'));

defined('ABSPATH') || define('ABSPATH', realpath($_webroot . $_wp_path) . '/');

define('WP_CONTENT_DIR',  $_webroot);
define('WP_CONTENT_URL',  env('WP_CONTENT_URL', WP_HOME));
define('WP_PLUGIN_DIR',   $_webroot . '/extensions/plugins');
define('WP_PLUGIN_URL',   WP_HOME . '/extensions/plugins');
define('WPMU_PLUGIN_DIR', $_webroot . '/extensions/components');
define('WPMU_PLUGIN_URL', WP_HOME . '/extensions/components');

unset($_webroot, $_wp_path);

// -------------------------------------------------------------------------
// Deprecated DIR_* constants
//
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
defined('DIR_PLUGINS') || define('DIR_PLUGINS',  DIR_WWW  . 'extensions' . DS . 'plugins' . DS);
