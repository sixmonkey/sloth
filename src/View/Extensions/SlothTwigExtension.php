<?php

declare(strict_types=1);
namespace Sloth\View\Extensions;

use Illuminate\Contracts\Container\BindingResolutionException;
use Override;
use Sloth\Core\Application;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

/**
 * Sloth Twig Extension.
 *
 * Registers Sloth-specific functions, filters and tests with the Twig
 * template engine. Also exposes WordPress core functions to Twig templates
 * via a `fn` global and dedicated TwigFunction wrappers.
 *
 * ## Usage in Twig templates
 *
 * ```twig
 * {# Call any PHP or WordPress function via fn namespace #}
 * {{ fn.get_the_title() }}
 *
 * {# Use registered functions directly #}
 * {{ get_field('my_field') }}
 * {{ meta('my_key', post.ID) }}
 * {{ module('hero', { title: 'Hello' }) }}
 *
 * {# Use registered filters #}
 * {{ phone_number | tel }}
 * {{ slug_string | sanitize }}
 * ```
 *
 * @since 1.0.0
 */
class SlothTwigExtension extends AbstractExtension
{
    /**
     * @param Application $container the Sloth application container
     *
     * @since 1.0.0
     */
    public function __construct(protected Application $container)
    {
    }

    /**
     * Return the unique extension name.
     *
     * @since 1.0.0
     */
    public function getName(): string
    {
        return 'sloth';
    }

    /**
     * Register Twig tests.
     *
     * Available in templates:
     * - `value is string` — checks if a value is a string
     *
     * @return list<TwigTest>
     *
     * @since 1.0.0
     */
    #[Override]
    public function getTests(): array
    {
        return [
            new TwigTest('string', fn ($value): bool => is_string($value)),
        ];
    }

    /**
     * Register global Twig variables.
     *
     * Exposes `fn` as a proxy object so any PHP or WordPress function can
     * be called from Twig using `{{ fn.function_name(args) }}`.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getGlobals(): array
    {
        return [
            'fn' => $this,
        ];
    }

    /**
     * Proxy any PHP or WordPress function call through the `fn` global.
     *
     * Called automatically by Twig when `fn.some_function()` is used
     * in a template.
     *
     * @param string       $name      the function name to call
     * @param array<mixed> $arguments arguments to pass
     *
     * @since 1.0.0
     */
    public function __call(string $name, array $arguments): mixed
    {
        return call_user_func_array($name, $arguments);
    }

    /**
     * Register Twig filters.
     *
     * Built-in filters:
     * - `hyphenate` — deprecated, returns input unchanged
     * - `debug`     — dumps the value using Sloth's debug() helper
     * - `print_r`   — alias for debug
     * - `tel`       — wraps a phone number in a tel: URI
     * - `sanitize`  — runs sanitize_title() on a string
     *
     * Additional filters can be registered via `theme.twig.filters` config:
     *
     * ```php
     * // app/config/theme.php
     * return [
     *     'twig' => [
     *         'filters' => [
     *             new TwigFilter('my_filter', fn($value) => transform($value)),
     *         ],
     *     ],
     * ];
     * ```
     *
     * @return list<TwigFilter>
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFilters(): array
    {
        $filters = [
            new TwigFilter('hyphenate', function (string $input): string {
                _deprecated_function('The "hyphenate" Twig filter', '1.0', 'Use CSS "hyphens: auto" instead.');

                return $input;
            }),

            // Dump a variable using Sloth's debug() helper
            new TwigFilter('debug', fn ($input): mixed => debug($input)),

            // Alias for debug
            new TwigFilter('print_r', fn ($input): mixed => debug($input)),

            // Convert a phone number to a tel: URI — strips all non-numeric characters except +
            new TwigFilter('tel', fn ($phone) => 'tel:' . preg_replace("/[^0-9\+]/", '', (string) $phone)),

            // Sanitize a string for use as a WordPress slug
            new TwigFilter('sanitize', fn ($string) => sanitize_title($string)),
        ];

        // Merge in any additional filters registered via theme config
        if (config('theme.twig.filters')) {
            return array_merge($filters, config('theme.twig.filters'));
        }

        return $filters;
    }

    /**
     * Register Twig functions.
     *
     * Built-in functions mirror their WordPress equivalents unless noted.
     * Additional functions can be registered via `theme.twig.functions` config:
     *
     * ```php
     * // app/config/theme.php
     * return [
     *     'twig' => [
     *         'functions' => [
     *             new TwigFunction('my_function', fn() => my_function()),
     *         ],
     *     ],
     * ];
     * ```
     *
     * @throws BindingResolutionException
     *
     * @return list<TwigFunction>
     *
     * @since 1.0.0
     */
    #[Override]
    public function getFunctions(): array
    {
        $functions = [
            // Render a Sloth module and return its output as a string
            new TwigFunction(
                'module',
                function (string $name, array $values = [], array $options = []): string|false {
                    ob_start();
                    module($name, $values, $options);

                    return ob_get_clean();
                },
            ),

            // -------------------------------------------------------------------------
            // WordPress theme functions
            // -------------------------------------------------------------------------

            // Fires the wp_head action — outputs meta tags, scripts, styles etc.
            new TwigFunction('wp_head', 'wp_head'),

            // Fires the wp_footer action — outputs scripts registered for the footer
            new TwigFunction('wp_footer', 'wp_footer'),

            // Outputs the body class attribute value for the current page
            new TwigFunction('body_class', fn ($class = '') => body_class($class)),

            // Outputs post class attribute value for the current or given post
            new TwigFunction('post_class', fn ($class = '', $id = null) => post_class($class, $id)),

            // -------------------------------------------------------------------------
            // WordPress formatting functions
            // -------------------------------------------------------------------------

            // Adds paragraph tags and line breaks to text
            new TwigFunction('wpautop', fn ($text, $br = true) => wpautop($text, $br)),

            // Trims text to a specified number of words
            new TwigFunction(
                'wp_trim_words',
                fn ($text, $num_words = 55, $more = null) => wp_trim_words($text, $num_words, $more),
            ),

            // -------------------------------------------------------------------------
            // ACF
            // -------------------------------------------------------------------------

            // Returns the value of an ACF field for the current or given post
            new TwigFunction('get_field', fn ($field_name, $post = null) => get_field($field_name, $post)),

            // -------------------------------------------------------------------------
            // Dynamic function call
            // -------------------------------------------------------------------------

            // Allows calling any PHP or WordPress function from Twig:
            // {{ function('my_function', arg1, arg2) }}
            new TwigFunction('function', function ($functionName) {
                $args = func_get_args();
                // Remove the function name from the arguments — it's the first element
                array_shift($args);

                if (is_string($functionName)) {
                    $functionName = trim($functionName);
                }

                return call_user_func_array($functionName, $args);
            }),

            // -------------------------------------------------------------------------
            // Meta data
            // -------------------------------------------------------------------------

            // Retrieve meta data from any WordPress object (post, comment, user, term)
            // Usage: {{ meta('my_key') }} or {{ meta('my_key', post.ID, 'post', true) }}
            new TwigFunction(
                'meta',
                fn ($key, $id = null, $context = 'post', $single = true) => get_metadata(
                    $context,
                    $id ?? get_the_ID(),
                    $key,
                    $single,
                ),
            ),

            // -------------------------------------------------------------------------
            // Gettext / i18n functions
            // -------------------------------------------------------------------------

            new TwigFunction('translate', fn ($text, $domain = 'default') => translate($text, $domain)),
            new TwigFunction('__', fn ($text, $domain = 'default') => __($text, $domain)),
            new TwigFunction('_e', fn ($text, $domain = 'default') => _e($text, $domain)),
            new TwigFunction(
                '_n',
                fn ($single, $plural, $number, $domain = 'default') => _n($single, $plural, $number, $domain),
            ),
            new TwigFunction('_x', fn ($text, $context, $domain = 'default') => _x($text, $context, $domain)),
            new TwigFunction('_ex', fn ($text, $context, $domain = 'default') => _ex($text, $context, $domain)),
            new TwigFunction(
                '_nx',
                fn ($single, $plural, $number, $context, $domain = 'default') => _nx(
                    $single,
                    $plural,
                    $number,
                    $context,
                    $domain,
                ),
            ),
            new TwigFunction(
                '_n_noop',
                fn ($singular, $plural, $domain = 'default') => _n_noop($singular, $plural, $domain),
            ),
            new TwigFunction(
                '_nx_noop',
                fn ($singular, $plural, $context, $domain = 'default') => _nx_noop(
                    $singular,
                    $plural,
                    $context,
                    $domain,
                ),
            ),
            new TwigFunction(
                'translate_nooped_plural',
                fn ($nooped_plural, $count, $domain = 'default') => translate_nooped_plural(
                    $nooped_plural,
                    $count,
                    $domain,
                ),
            ),
            new TwigFunction('url', fn ($path = null) => url($path)),
        ];

        // -------------------------------------------------------------------------
        // Polylang i18n (optional plugin)
        // -------------------------------------------------------------------------

        // Only register Polylang functions when the plugin is active

        if (function_exists('pll_e')) {
            $functions[] = new TwigFunction('pll_e', 'pll_e');
        }

        if (function_exists('pll__')) {
            $functions[] = new TwigFunction('pll__', 'pll__');
        }

        // Merge in any additional functions registered via theme config
        if (config('theme.twig.functions')) {
            return array_merge($functions, config('theme.twig.functions'));
        }

        return $functions;
    }

    /**
     * Initialize the Twig runtime environment.
     *
     * Called by Twig when the extension is loaded. Currently a no-op —
     * kept for compatibility in case subclasses need it.
     *
     * @param Environment $environment the Twig environment instance
     *
     * @since 1.0.0
     */
    public function initRuntime(Environment $environment): void
    {
    }
}
