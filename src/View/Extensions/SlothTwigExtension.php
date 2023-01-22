<?php

namespace Sloth\View\Extensions;

use Sloth\Core\Application;
use Sloth\Facades\Configure;
use Twig_SimpleTest;
use Twig_SimpleFilter;
use Twig_Extension;
use \Org\Heigl\Hyphenator as h;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig\TwigTest;

class SlothTwigExtension extends AbstractExtension
{
    /**
     * @var \Sloth\Core\Application
     */
    protected $container;

    public function __construct(Application $container)
    {
        $this->container = $container;
    }

    /**
     * Define the extension name.
     *
     * @return string
     */
    public function getName()
    {
        return 'sloth';
    }

    public function getTests()
    {
        return [
            new TwigTest('string', function ($value) {
                return is_string($value);
            }),
        ];
    }

    /**
     * Register a global "fn" which can be used
     * to call any WordPress or core PHP functions.
     *
     * @return array
     */
    public function getGlobals()
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
     * @param array  $arguments
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
            new TwigFilter\('hyphenate', function ($input) {
                $input = ' ' . $input;
                $o     = new h\Options();
                $o->setHyphen('&shy;')
                  ->setMinWordLength(10)
                  ->setDefaultLocale('de_DE')
                  ->setFilters('Simple')
                  ->setTokenizers('Whitespace', 'Punctuation');
                $h = new h\Hyphenator();
                $h->setOptions($o);
                $hyphenate_string = $h->hyphenate($input);

                return new \Twig_Markup($hyphenate_string, 'UTF-8');
            }),
            new TwigFilter('debug', function ($input) {
                return debug($input);
            }),
            new TwigFilter('print_r', function ($input) {
                return debug($input);
            }),
            new TwigFilter('tel', function ($phone) {
                return 'tel:' . preg_replace("/[^0-9\+]/", "", $phone);
            }),
            new TwigFilter('sanitize',
                function ($string) {
                    return sanitize_title($string);
                }),
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
            new TwigFunction('module',
                function ($name, $values = [], $options = []) {
                    ob_start();
                    $GLOBALS['sloth']->container->callModule($name, $values, $options);

                    return ob_get_clean();
                }),
            /*
             * WordPress theme functions.
             */
            new TwigFunction('wp_head', 'wp_head'),
            new TwigFunction('wp_footer', 'wp_footer'),
            new TwigFunction('body_class', function ($class = '') {
                return body_class($class);
            }),
            new TwigFunction('post_class', function ($class = '', $id = null) {
                return post_class($class, $id);
            }),
            /*
             * WordPress formatting functions.
             */
            new TwigFunction('wpautop', function ($text, $br = true) {
                return wpautop($text, $br);
            }),
            new TwigFunction('wp_trim_words', function ($text, $num_words = 55, $more = null) {
                return wp_trim_words($text, $num_words, $more);
            }),
            new TwigFunction('get_field', function ($field_name, $post = null) {
                return get_field($field_name, $post);
            }),
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
            new TwigFunction('meta', function ($key, $id = null, $context = 'post', $single = true) {
                return meta($key, $id, $context, $single);
            }),
            /*
             * Gettext functions.
             */
            new TwigFunction('translate', function ($text, $domain = 'default') {
                return translate($text, $domain);
            }),
            new TwigFunction('__', function ($text, $domain = 'default') {
                return __($text, $domain);
            }),
            new TwigFunction('_e', function ($text, $domain = 'default') {
                return _e($text, $domain);
            }),
            new TwigFunction('_n', function ($single, $plural, $number, $domain = 'default') {
                return _n($single, $plural, $number, $domain);
            }),
            new TwigFunction('_x', function ($text, $context, $domain = 'default') {
                return _x($text, $context, $domain);
            }),
            new TwigFunction('_ex', function ($text, $context, $domain = 'default') {
                return _ex($text, $context, $domain);
            }),
            new TwigFunction('_nx', function ($single, $plural, $number, $context, $domain = 'default') {
                return _nx($single, $plural, $number, $context, $domain);
            }),
            new TwigFunction('_n_noop', function ($singular, $plural, $domain = 'default') {
                return _n_noop($singular, $plural, $domain);
            }),
            new TwigFunction('_nx_noop', function ($singular, $plural, $context, $domain = 'default') {
                return _nx_noop($singular, $plural, $context, $domain);
            }),
            new TwigFunction('translate_nooped_plural',
                function ($nooped_plural, $count, $domain = 'default') {
                    return translate_nooped_plural($nooped_plural, $count, $domain);
                }),
            new TwigFunction('pll_e', 'pll_e'),
            new TwigFunction('pll__', 'pll__'),
        ];


        if (Configure::read('theme.twig.functions')) {
            $theme_functions = Configure::read('theme.twig.functions');
            $functions       = array_merge($functions, $theme_functions);
        }

        return $functions;
    }

    public function initRuntime(\Twig_Environment $environment)
    {

    }
}
