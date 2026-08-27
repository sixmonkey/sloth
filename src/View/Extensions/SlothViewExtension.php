<?php

declare(strict_types=1);
namespace Sloth\View\Extensions;

use Override;

/**
 * Sloth's built-in view extension.
 *
 * Registers all framework-provided helpers, directives and shared variables.
 * Discovered automatically alongside theme extensions.
 *
 * @since 1.0.0
 */
class SlothViewExtension extends AbstractViewExtension
{
    /**
     * Built-in helpers — registered as Twig filters and Blade echo helpers.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getHelpers(): array
    {
        return [
            // Dump a variable using Sloth's debug() helper
            'debug'   => debug(...),
            'print_r' => debug(...),

            // Convert a phone number to a tel: URI
            'tel' => fn ($phone) => 'tel:' . preg_replace("/[^0-9\+]/", '', (string) $phone),

            // Sanitize a string for use as a WordPress slug
            'sanitize' => fn ($string) => sanitize_title($string),

            // @deprecated
            'hyphenate' => function (string $input): string {
                _deprecated_function('The "hyphenate" Twig filter', '1.0', 'Use CSS "hyphens: auto" instead.');

                return $input;
            },
        ];
    }

    /**
     * Built-in directives — registered as Twig functions and Blade directives.
     *
     * @since 1.0.0
     */
    #[Override]
    public function getDirectives(): array
    {
        $directives = [
            // WordPress theme hooks
            'wp_head'    => 'wp_head',
            'wp_footer'  => 'wp_footer',
            'wp_title'   => 'wp_title',
            'body_class' => fn ($class = '') => body_class($class),
            'post_class' => fn ($class = '', $id = null) => post_class($class, $id),

            // WordPress formatting
            'wpautop'       => fn ($text, $br = true) => wpautop($text, $br),
            'wp_trim_words' => fn ($text, $num = 55, $more = null) => wp_trim_words($text, $num, $more),

            // ACF
            'get_field' => fn ($field, $post = null) => get_field($field, $post),

            // Meta
            'meta' => fn ($key, $id = null, $context = 'post', $single = true) => get_metadata(
                $context,
                $id ?? get_the_ID(),
                $key,
                $single,
            ),

            // Modules
            'module' => function (string $name, array $values = [], array $options = []): string|false {
                ob_start();
                module($name, $values, $options);

                return ob_get_clean();
            },

            // URL
            'url'   => url(...),
            'asset' => fn (string $path): string => app('url')->asset($path),

            // Options
            'options' => fn (?string $key = null, mixed $default = null): mixed => options($key, $default),

            // Dynamic function call
            'function' => fn (string $functionName, mixed ...$args): mixed => call_user_func_array($functionName, $args),

            // i18n
            'translate'               => fn ($text, $domain = 'default') => translate($text, $domain),
            '__'                      => fn ($text, $domain = 'default') => __($text, $domain),
            '_e'                      => fn ($text, $domain = 'default') => _e($text, $domain),
            '_n'                      => fn ($single, $plural, $number, $domain = 'default') => _n($single, $plural, $number, $domain),
            '_x'                      => fn ($text, $context, $domain = 'default') => _x($text, $context, $domain),
            '_ex'                     => fn ($text, $context, $domain = 'default') => _ex($text, $context, $domain),
            '_nx'                     => fn ($s, $p, $n, $c, $d = 'default') => _nx($s, $p, $n, $c, $d),
            '_n_noop'                 => fn ($s, $p, $d = 'default') => _n_noop($s, $p, $d),
            '_nx_noop'                => fn ($s, $p, $c, $d = 'default') => _nx_noop($s, $p, $c, $d),
            'translate_nooped_plural' => fn ($np, $c, $d = 'default') => translate_nooped_plural($np, $c, $d),
        ];

        // Polylang — only when active
        if (function_exists('pll_e')) {
            $directives['pll_e'] = 'pll_e';
        }

        if (function_exists('pll__')) {
            $directives['pll__'] = 'pll__';
        }

        return $directives;
    }

    /**
     * Shared variables — available in all templates.
     *
     * @since 1.0.0
     */
    #[Override]
    public function share(): array
    {
        return [
            'app' => app(),
        ];
    }
}
