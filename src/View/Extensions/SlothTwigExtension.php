<?php

namespace Sloth\View\Extensions;

use Sloth\Core\Application;
use Twig_SimpleTest;
use Twig_SimpleFilter;
use Twig_SimpleFunction;
use Twig_Extension;
use \Org\Heigl\Hyphenator as h;

class SlothTwigExtension extends Twig_Extension {
	/**
	 * @var \Sloth\Core\Application
	 */
	protected $container;

	public function __construct( Application $container ) {
		$this->container = $container;
	}

	/**
	 * Define the extension name.
	 *
	 * @return string
	 */
	public function getName() {
		return 'sloth';
	}

	public function getTests() {
		return [
			new Twig_SimpleTest( 'string', function ( $value ) {
				return is_string( $value );
			} ),
		];
	}

	/**
	 * Register a global "fn" which can be used
	 * to call any WordPress or core PHP functions.
	 *
	 * @return array
	 */
	public function getGlobals() {
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
	 * @param array $arguments
	 *
	 * @return mixed
	 */
	public function __call( $name, array $arguments ) {
		return call_user_func_array( $name, $arguments );
	}


	/**
	 * Register a list of filters available into Twig templates.
	 *
	 * @return array|\Twig_SimpleFunction[]
	 */
	public function getFilters() {
		return [
			new Twig_SimpleFilter( 'hyphenate', function ( $input ) {
				$input = ' ' . $input;
				$o     = new h\Options();
				$o->setHyphen( '&shy;' )
				  ->setMinWordLength( 10 )
				  ->setDefaultLocale( 'de_DE' )
				  ->setFilters( 'Simple' )
				  ->setTokenizers( 'Whitespace', 'Punctuation' );
				$h = new h\Hyphenator();
				$h->setOptions( $o );
				$hyphenate_string = $h->hyphenate( $input );

				return new \Twig_Markup( $hyphenate_string, 'UTF-8' );
			} ),
			new Twig_SimpleFilter( 'debug', function ( $input ) {
				return debug( $input );
			} ),
			new Twig_SimpleFilter( 'print_r', function ( $input ) {
				return debug( $input );
			} ),
		];

	}

	/**
	 * Register a list of functions available into Twig templates.
	 *
	 * @return array|\Twig_SimpleFunction[]
	 */
	public function getFunctions() {
		return [
			new Twig_SimpleFunction( 'module',
				function ( $name, $values = [], $options = [] ) {
					$GLOBALS['sloth']->container->callModule( $name, $values, $options );
				} ),
			/*
			 * WordPress theme functions.
			 */
			new Twig_SimpleFunction( 'wp_head', 'wp_head' ),
			new Twig_SimpleFunction( 'wp_footer', 'wp_footer' ),
			new Twig_SimpleFunction( 'body_class', function ( $class = '' ) {
				return body_class( $class );
			} ),
			new Twig_SimpleFunction( 'post_class', function ( $class = '', $id = null ) {
				return post_class( $class, $id );
			} ),
			/*
			 * WordPress formatting functions.
			 */
			new Twig_SimpleFunction( 'wpautop', function ( $text, $br = true ) {
				return wpautop( $text, $br );
			} ),
			new Twig_SimpleFunction( 'wp_trim_words', function ( $text, $num_words = 55, $more = null ) {
				return wp_trim_words( $text, $num_words, $more );
			} ),
			new Twig_SimpleFunction( 'get_field', function ( $field_name, $post = null ) {
				return get_field( $field_name, $post );
			} ),
			/*
			 * Use this to call any core, WordPress or user defined functions.
			 */
			new Twig_SimpleFunction( 'function', function ( $functionName ) {
				$args = func_get_args();
				// By default, the function name should always be the first argument.
				// This remove it from the arguments list.
				array_shift( $args );

				if ( is_string( $functionName ) ) {
					$functionName = trim( $functionName );
				}

				return call_user_func_array( $functionName, $args );
			} ),
			/*
			 * Retrieve any meta data from post, comment, user, ...
			 */
			new Twig_SimpleFunction( 'meta', function ( $key, $id = null, $context = 'post', $single = true ) {
				return meta( $key, $id, $context, $single );
			} ),
			/*
			 * Gettext functions.
			 */
			new Twig_SimpleFunction( 'translate', function ( $text, $domain = 'default' ) {
				return translate( $text, $domain );
			} ),
			new Twig_SimpleFunction( '__', function ( $text, $domain = 'default' ) {
				return __( $text, $domain );
			} ),
			new Twig_SimpleFunction( '_e', function ( $text, $domain = 'default' ) {
				return _e( $text, $domain );
			} ),
			new Twig_SimpleFunction( '_n', function ( $single, $plural, $number, $domain = 'default' ) {
				return _n( $single, $plural, $number, $domain );
			} ),
			new Twig_SimpleFunction( '_x', function ( $text, $context, $domain = 'default' ) {
				return _x( $text, $context, $domain );
			} ),
			new Twig_SimpleFunction( '_ex', function ( $text, $context, $domain = 'default' ) {
				return _ex( $text, $context, $domain );
			} ),
			new Twig_SimpleFunction( '_nx', function ( $single, $plural, $number, $context, $domain = 'default' ) {
				return _nx( $single, $plural, $number, $context, $domain );
			} ),
			new Twig_SimpleFunction( '_n_noop', function ( $singular, $plural, $domain = 'default' ) {
				return _n_noop( $singular, $plural, $domain );
			} ),
			new Twig_SimpleFunction( '_nx_noop', function ( $singular, $plural, $context, $domain = 'default' ) {
				return _nx_noop( $singular, $plural, $context, $domain );
			} ),
			new Twig_SimpleFunction( 'translate_nooped_plural',
				function ( $nooped_plural, $count, $domain = 'default' ) {
					return translate_nooped_plural( $nooped_plural, $count, $domain );
				} ),
			new Twig_SimpleFunction( 'pll_e', 'pll_e' ),
			new Twig_SimpleFunction( 'pll_', 'pll_' ),
		];
	}

	public function initRuntime( \Twig_Environment $environment ) {

	}
}
