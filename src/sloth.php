<?php
/**
 * Plugin Name: Sloth
 * Description: Sloth Framework Bootstrap
 * Version: 1.0.0
 */

use Sloth\Core\Application;

add_action('after_setup_theme', function (): void {
    Application::configure()->boot();
}, 0);
