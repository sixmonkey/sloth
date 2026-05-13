<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Relative URLs
    |--------------------------------------------------------------------------
    |
    | Convert absolute WordPress URLs to relative URLs. Useful for
    | environments where the site URL may differ from the actual domain.
    |
    */

    'relative_urls'   => false,
    'relative_links'  => false,
    'relative_uploads' => false,

    /*
    |--------------------------------------------------------------------------
    | WP JSON API
    |--------------------------------------------------------------------------
    |
    | Override the base URL prefix for the WordPress REST API.
    |
    | Example: 'wp_json' => ['base_url' => 'api']
    |
    */

    'wp_json' => [
        'base_url' => 'wp-json',
    ],

];
