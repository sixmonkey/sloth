<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hide WordPress Updates
    |--------------------------------------------------------------------------
    |
    | Suppress WordPress update notifications in the admin for core, plugins
    | and themes. Useful for client projects where the developer manages
    | updates. Defaults to false — opt-in.
    |
    */

    'hide_updates' => [
        'core'    => false,
        'plugins' => false,
        'themes'  => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Footer
    |--------------------------------------------------------------------------
    |
    | Display Sloth and WordPress version info in the admin footer.
    |
    */

    'footer' => true,

    /*
    |--------------------------------------------------------------------------
    | Clean Up Admin Menu
    |--------------------------------------------------------------------------
    |
    | Remove duplicate PHP pages from the admin menu.
    |
    */

    'cleanup_menu' => true,

];
