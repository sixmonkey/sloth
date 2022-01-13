<?php

/*----------------------------------------------------*/
// Directory separator
/*----------------------------------------------------*/
defined('DS') ? DS : define('DS', DIRECTORY_SEPARATOR);
/*----------------------------------------------------*/
// Bootstrap Sloth and WordPress
/*----------------------------------------------------*/
require_once dirname(__DIR__) . DS . 'bootstrap.php';
/*----------------------------------------------------*/
// Sets up WordPress vars and included files
/*----------------------------------------------------*/
require_once ABSPATH . 'wp-settings.php';
