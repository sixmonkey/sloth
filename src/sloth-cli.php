#!/usr/bin/php -q
<?php

namespace Sloth\Scaffolder;

require __DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
require __DIR__ . DIRECTORY_SEPARATOR . 'bootstrap.php';

use League\CLImate\CLImate;
use Sloth\Utility\Utility;

if (! isset($argv[1])) {
    throw new \Exception('Please give an action! E.g. sloth.php build_module');
}

$scaffolder      = new Scaffolder();
$climate         = new CLImate;
$scaffold_action = Utility::underscore($argv[1]);

if (method_exists($scaffolder, $scaffold_action)) {
    define('WP_USE_THEMES', true);

    include($scaffolder->_get_wordpress_install_dir() . 'wp-load.php');
    call_user_func([$scaffolder, $scaffold_action]);
} else {
    $climate->error(sprintf('Unknown action %s', $scaffold_action));
}
