<?php

declare(strict_types=1);

/**
 * WordPress Event Bridge Configuration
 *
 * This file defines which WordPress hooks are bridged to Laravel events.
 * Each entry maps a WordPress hook name to its type ('action' or 'filter').
 *
 * When a bridged hook fires, it dispatches a Laravel event with the name
 * 'wp:{hook}'. Listeners receive a WpHookFired instance containing the
 * hook name, arguments, type, and (for filters) the modifiable result.
 *
 * Usage in a service provider or listener:
 *
 *   // Listen to a WordPress action
 *   Event::listen('wp:wp_loaded', function (WpHookFired $event) {
 *       // WordPress is fully loaded
 *   });
 *
 *   // Modify a WordPress filter result
 *   Event::listen('wp:the_content', function (WpHookFired $event) {
 *       $event->result = apply_custom_transformation($event->result);
 *   });
 *
 * @since 1.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | WordPress Hook Bridge
    |--------------------------------------------------------------------------
    |
    | WordPress hooks to bridge to Laravel events.
    |
    | Format: 'hook_name' => 'action' | 'filter'
    |
    | Actions are fire-and-forget: listeners can react but cannot modify values.
    | Filters are bidirectional: listeners can set $event->result to change the
    | value returned by apply_filters().
    |
    | The bridge only dispatches hooks that have active Laravel listeners
    | (hasListeners check), so adding hooks here has near-zero performance
    | impact unless something is actually listening.
    |
    */

    'bridge' => [

        // ─── Core Lifecycle ───────────────────────────────────────────
        // These hooks fire during WordPress bootstrap, before theme output.

        'muplugins_loaded'  => 'action',  // MU-plugins loaded (earliest)
        'plugins_loaded'    => 'action',  // All plugins loaded
        'after_setup_theme' => 'action',  // Theme functions.php loaded
        'init'              => 'action',  // WordPress fully initialized
        'wp_loaded'         => 'action',  // All WordPress setup complete

        // ─── Template Rendering ───────────────────────────────────────
        // These hooks fire during page rendering.

        'template_redirect' => 'action',  // Before template is loaded
        'wp_head'           => 'action',  // Inside <head> tag
        'wp_footer'         => 'action',  // Before </body> tag

        // ─── Important Filters ────────────────────────────────────────
        // Bidirectional: listeners can modify the filtered value.

        'the_content'  => 'filter',       // Post content before display
        'the_title'    => 'filter',       // Post title before display
        'the_excerpt'  => 'filter',       // Post excerpt before display
        'body_class'   => 'filter',       // HTML body classes

        // ─── Request End ─────────────────────────────────────────────
        'shutdown'     => 'action',       // PHP shutdown (last chance)

    ],

];
