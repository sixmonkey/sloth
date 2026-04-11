<?php

namespace Sloth\View\Extensions;

use Sloth\Core\Application;
use Sloth\Facades\Configure;
use Twig_SimpleTest;
use Twig_SimpleFilter;
use Twig_Extension;
use Org\Heigl\Hyphenator as h;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class SlothTwigExtension extends AbstractExtension
{
    public function __construct(protected \Sloth\Core\Application $container)
    {
    }

    /**
     * Define the extension name.
     */
    public function getName(): string
    {
        return 'sloth';
    }

    public function getTests()
    {
        return [
            new TwigTest('string', fn($value): bool => is_string($value)),
        ];
    }

    /**
     * Register a global "fn" which can be used
     * to call any WordPress or core PHP functions.
     */
    public function getGlobals(): array
    {
        return [
            'fn' => $this,
        ];
    }

    /**
     * Allow developers to call core php and WordPress functions
     * using the `fn` namespace inside their templates.
     * Linked to the global call only...
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        return call_user_func_array($name, $arguments);
    }


    /**
     * Register a list of filters available into Twig templates.
     *
     * @return array|\TwigFunction[]
     */
    public function getFilters()
    {
        $filters = [
            new TwigFilter('hyphenate', function ($input): \Twig_Markup {
                $input = ' ' . $input;
                $o     = new h\Options();
                $o->setHyphen('&shy;')
                  ->setMinWordLength(10)
                  ->setDefaultLocale('de_DE')
                  ->setFilters('Simple')
                  ->setTokenizers('Whitespace');
                $h = new h\Hyphenator();
                $h->setOptions($o);

                $hyphenate_string = $h->hyphenate($input);

                return new \Twig_Markup($hyphenate_string, 'UTF-8');
            }),
            new TwigFilter('debug', fn($input): mixed => debug($input)),
            new TwigFilter('print_r', fn($input): mixed => debug($input)),
            new TwigFilter('tel', fn($phone) => 'tel:' . preg_replace("/[^0-9\+]/", "", (string) $phone)),
            new TwigFilter(
                'sanitize',
                fn($string) => sanitize_title($string)
            ),
        ];


        if (Configure::read('theme.twig.filters')) {
            $theme_filters = Configure::read('theme.twig.filters');
            $filters       = array_merge($filters, $theme_filters);
        }

        return $filters;
    }

    /**
     * Register a list of functions available into Twig templates.
     *
     * @return array|\TwigFunction[]
     */
    public function getFunctions()
    {
        $functions = [
            new TwigFunction(
                'module',
                function ($name, $values = [], $options = []): string|false {
                    ob_start();
                    $GLOBALS['sloth']->container->callModule($name, $values, $options);

                    return ob_get_clean();
                }
            ),
            /*
             * WordPress theme functions.
             */
            new TwigFunction('wp_head', 'wp_head'),
            new TwigFunction('wp_footer', 'wp_footer'),
            new TwigFunction('body_class', fn($class = '') => body_class($class)),
            new TwigFunction('post_class', fn($class = '', $id = null) => post_class($class, $id)),
            /*
             * WordPress formatting functions.
             */
            new TwigFunction('wpautop', fn($text, $br = true) => wpautop($text, $br)),
            new TwigFunction('wp_trim_words', fn($text, $num_words = 55, $more = null) => wp_trim_words($text, $num_words, $more)),
            new TwigFunction('get_field', fn($field_name, $post = null) => get_field($field_name, $post)),
            /*
             * Use this to call any core, WordPress or user defined functions.
             */
            new TwigFunction('function', function ($functionName) {
                $args = func_get_args();
                // By default, the function name should always be the first argument.
                // This remove it from the arguments list.
                array_shift($args);

                if (is_string($functionName)) {
                    $functionName = trim($functionName);
                }

                return call_user_func_array($functionName, $args);
            }),
            /*
             * Retrieve any meta data from post, comment, user, ...
             */
            new TwigFunction('meta', fn($key, $id = null, $context = 'post', $single = true) => meta($key, $id, $context, $single)),
            /*
             * Gettext functions.
             */
            new TwigFunction('translate', fn($text, $domain = 'default') => translate($text, $domain)),
            new TwigFunction('__', fn($text, $domain = 'default') => __($text, $domain)),
            new TwigFunction('_e', fn($text, $domain = 'default') => _e($text, $domain)),
            new TwigFunction('_n', fn($single, $plural, $number, $domain = 'default') => _n($single, $plural, $number, $domain)),
            new TwigFunction('_x', fn($text, $context, $domain = 'default') => _x($text, $context, $domain)),
            new TwigFunction('_ex', fn($text, $context, $domain = 'default') => _ex($text, $context, $domain)),
            new TwigFunction('_nx', fn($single, $plural, $number, $context, $domain = 'default') => _nx($single, $plural, $number, $context, $domain)),
            new TwigFunction('_n_noop', fn($singular, $plural, $domain = 'default') => _n_noop($singular, $plural, $domain)),
            new TwigFunction('_nx_noop', fn($singular, $plural, $context, $domain = 'default') => _nx_noop($singular, $plural, $context, $domain)),
            new TwigFunction(
                'translate_nooped_plural',
                fn($nooped_plural, $count, $domain = 'default') => translate_nooped_plural($nooped_plural, $count, $domain)
            ),
            new TwigFunction('pll_e', 'pll_e'),
            new TwigFunction('pll__', 'pll__'),
        ];


        if (Configure::read('theme.twig.functions')) {
            $theme_functions = Configure::read('theme.twig.functions');
            $functions       = array_merge($functions, $theme_functions);
        }

        return $functions;
    }

    public function initRuntime(\Twig_Environment $environment) {}
}
