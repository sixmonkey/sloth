<?php

declare(strict_types=1);
namespace Sloth\View\Extensions;

/**
 * Base class for view extensions.
 *
 * Drop a subclass in app/Extensions/View/ or theme/Extensions/View/
 * and Sloth discovers and registers it automatically.
 *
 * ## Helpers
 *
 * Registered as Twig filters and Blade echo helpers.
 * Use for transforming values — currency, date formatting etc.
 *
 * ```php
 * public function getHelpers(): array
 * {
 *     return [
 *         'currency' => fn(float $v, string $s = '€') => number_format($v, 2) . ' ' . $s,
 *         'sanitize' => fn($v) => sanitize_title($v),
 *     ];
 * }
 * ```
 *
 * ```twig
 * {{ price | currency }}
 * {{ price | currency('USD') }}
 * ```
 *
 * ## Directives
 *
 * Registered as Twig functions and Blade directives.
 * Use for generating output or calling actions.
 *
 * ```php
 * public function getDirectives(): array
 * {
 *     return [
 *         'wp_head'    => 'wp_head',
 *         'module'     => fn(...$args) => module(...$args),
 *         'pll__'      => 'pll__',
 *     ];
 * }
 * ```
 *
 * ```twig
 * {{ wp_head() }}
 * {{ module('hero') }}
 * ```
 *
 * ## Shared variables
 *
 * Passed to all templates via View::share() — available in both Twig
 * (as globals) and Blade (as shared variables).
 *
 * ```php
 * public function share(): array
 * {
 *     return [
 *         'options' => app('options'),
 *     ];
 * }
 * ```
 *
 * ## Array formats
 *
 * All three methods support the same formats — analogous to getHooks()/getFilters():
 *
 * ```php
 * return [
 *     'name',                          // string → callable 'name'
 *     'alias' => 'original_function', // string → string callable
 *     'name'  => fn(...$args) => ..., // string → closure
 * ];
 * ```
 *
 * @since 1.0.0
 */
abstract class AbstractViewExtension
{
    /**
     * Return helpers — registered as Twig filters and Blade echo helpers.
     *
     * @return array<int|string, callable|string>
     *
     * @since 1.0.0
     */
    public function getHelpers(): array
    {
        return [];
    }

    /**
     * Return directives — registered as Twig functions and Blade directives.
     *
     * @return array<int|string, callable|string>
     *
     * @since 1.0.0
     */
    public function getDirectives(): array
    {
        return [];
    }

    /**
     * Return shared variables — available in all templates via View::share().
     *
     * In Twig, shared variables are also registered as globals.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function share(): array
    {
        return [];
    }
}
