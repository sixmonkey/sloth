<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Navigation Menus
    |--------------------------------------------------------------------------
    |
    | Register WordPress navigation menu locations. Keys are the location
    | identifier, values are the display name shown in the WordPress admin.
    |
    | Example:
    |   'menus' => [
    |       'primary'   => 'Primary Navigation',
    |       'footer'    => 'Footer Navigation',
    |   ],
    |
    */

    'menus' => [],

    /*
    |--------------------------------------------------------------------------
    | Image Sizes
    |--------------------------------------------------------------------------
    |
    | Register custom image sizes. Each entry maps a size name to its
    | dimensions and cropping behaviour.
    |
    | Example:
    |   'image_sizes' => [
    |       'hero'      => [1920, 1080, true],
    |       'thumbnail' => [400, 300, true],
    |   ],
    |
    */

    'image_sizes' => [],

    /*
    |--------------------------------------------------------------------------
    | Process ACF Fields
    |--------------------------------------------------------------------------
    |
    | When enabled, ACF field values are automatically processed and merged
    | into the model's attributes on retrieval.
    |
    */

    'process_acf' => false,

];
