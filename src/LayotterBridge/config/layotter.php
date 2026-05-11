<?php

declare(strict_types=1);

/**
 * Layotter Bridge Configuration.
 *
 * Publish this file to your project to customize the Layotter integration:
 *
 * ```bash
 * wp sloth vendor:publish --provider="Sloth\LayotterBridge\LayotterBridgeServiceProvider" --tag=config
 * ```
 *
 * Uncomment and set only the values you want to override.
 *
 * @since 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Row Layouts
    |--------------------------------------------------------------------------
    |
    | Restrict the available row layouts globally. Individual post types can
    | override this via $layotter = ['allowed_row_layouts' => [...]] on the model.
    |
    | When not set, Layotter's own defaults apply.
    |
    | 'row_layouts' => ['full', '1/2', '1/3', '2/3'],
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Custom Column Classes
    |--------------------------------------------------------------------------
    |
    | Override the default Bootstrap 12-column grid classes.
    | Format: ['fraction' => 'css-class']
    |
    | When not set, defaults to col-lg-1 through col-lg-12.
    |
    | 'custom_classes' => [
    |     '1/2' => 'col-md-6',
    |     '1/3' => 'col-md-4',
    |     '2/3' => 'col-md-8',
    | ],
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Prepare Fields
    |--------------------------------------------------------------------------
    |
    | Whether to prepare ACF fields before rendering Layotter elements.
    |
    | 'prepare_fields' => true,
    |
    */
];
