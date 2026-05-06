<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    |
    | The default connection used by Eloquent models. Override in
    | app/config/database.php to change the default or add connections.
    |
    */

    'default' => env('DB_CONNECTION', 'wordpress'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | All registered database connections. The 'wordpress' connection is
    | pre-configured from WordPress constants and .env values.
    |
    | Theme developers may add additional connections in app/config/database.php:
    |
    |     return [
    |         'connections' => [
    |             'external' => [
    |                 'driver'   => 'mysql',
    |                 'host'     => env('EXTERNAL_DB_HOST'),
    |                 'database' => env('EXTERNAL_DB_NAME'),
    |                 'username' => env('EXTERNAL_DB_USER'),
    |                 'password' => env('EXTERNAL_DB_PASSWORD'),
    |                 'charset'  => 'utf8mb4',
    |                 'collation'=> 'utf8mb4_unicode_ci',
    |                 'prefix'   => '',
    |             ],
    |         ],
    |     ];
    |
    | Then use it in a model: protected $connection = 'external';
    |
    */

    'connections' => [
        'wordpress' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost'),
            'database' => env('DB_NAME', defined('DB_NAME') ? DB_NAME : ''),
            'username' => env('DB_USER', defined('DB_USER') ? DB_USER : ''),
            'password' => env('DB_PASSWORD', defined('DB_PASSWORD') ? DB_PASSWORD : ''),
            'prefix' => env('DB_PREFIX', defined('DB_PREFIX') ? DB_PREFIX : 'wp_'),
            'charset' => env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
            'collation' => env('DB_COLLATION', defined('DB_COLLATION') ? DB_COLLATION : 'utf8mb4_unicode_ci'),
        ],
    ],

];
