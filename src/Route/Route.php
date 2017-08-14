<?php

namespace Sloth\Route;


final class Route {
	/**
	 * @var bool
	 */
	private static $dispatched = false;

	/**
	 * @var \FastRoute\simpleDispatcher
	 */
	private static $dispatcher;

	/**
	 * @var array
	 */
	private static $routes = [];
	/**
	 * Sloth\Route instance.
	 *
	 * @var \Sloth\Route\Route
	 */
	protected static $instance = null;

	/**
	 * The prefix used to name the custom route tag.
	 *
	 * @var string
	 */
	protected $rewrite_tag_prefix = 'sloth';

	/**
	 * @var array
	 */
	protected $regexes = [];

	protected $routeTargetDefaults = [
		'controller' => '\Sloth\Controller\BaseController',
		'action'     => 'index',
	];

	/**
	 * Retrieve Sloth class instance.
	 *
	 * @return \Sloth\Route\Route
	 */
	public static function instance() {
		if ( is_null( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Boot the router
	 */
	public function boot() {
		self::$dispatcher = \FastRoute\cachedDispatcher( function ( \FastRoute\RouteCollector $r ) {
			foreach ( self::$routes as $route ) {
				$r->addRoute( $route['httpMethod'], $route['route'], $route['template'] );
			}
			list( $static, $variable ) = $r->getData();

			foreach ( $static as $routes ) {
				foreach ( $routes as $route => $template ) {
					$this->regexes[] = $this->getRewriteRuleRegex( $route );
				}
			}
			foreach ( $variable as $routes ) {
				foreach ( $routes as $route ) {
					$this->regexes[] = $this->getRewriteRuleRegex( $route['regex'] );
				}
			}

		},
			[
				'cacheFile'     => DIR_CACHE . DS . 'Route' . DS . 'route.php',
				'cacheDisabled' => WP_DEBUG
			] );
	}

	/**
	 * add a Route to initial collection
	 *
	 * @param array|string httpMethod
	 * @param string $route
	 * @param string $template
	 */
	private static function addRoute( Array $httpMethod, $route, $template ) {
		if ( self::$dispatched ) {
			throw new Exception( 'Adding Routes is no longer possible. Please use your template\'s routes.php to define Routes.' );
		}
		self::$routes[] = [
			'httpMethod' => $httpMethod,
			'route'      => self::normalize( $route ),
			'template'   => $template,
		];
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function add( $route, $template ) {
		self::addRoute( [ 'GET', 'POST' ], $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function get( $route, $template ) {
		self::addRoute( 'GET', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function post( $route, $template ) {
		self::addRoute( 'POST', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function put( $route, $template ) {
		self::addRoute( 'PUT', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function patch( $route, $template ) {
		self::addRoute( 'PATCH', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function delete( $route, $template ) {
		self::addRoute( 'DELETE', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function head( $route, $template ) {
		self::addRoute( 'HEAD', $route, $template );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $template
	 */
	public function any( $route, $template ) {
		self::addRoute( [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD' ], $route, $template );
	}

	/**
	 * @param $route
	 *
	 * @return string
	 */
	private static function normalize( $route ) {
		/*
		 * prevent routes from breaking when redirected to trailingslash
		 */
		return rtrim( $route, '/' ) . '[/]';
	}

	/**
	 * dispatch it!
	 */
	public function dispatch() {
		global $wp_query, $wp;

		self::$dispatched = true;

		// Fetch method and URI from somewhere
		$httpMethod = $_SERVER['REQUEST_METHOD'];
		$uri        = $_SERVER['REQUEST_URI'];

		// Strip query string (?foo=bar) and decode URI
		if ( false !== $pos = strpos( $uri, '?' ) ) {
			$uri = substr( $uri, 0, $pos );
		}
		$uri          = rawurldecode( $uri );
		$template_dir = realpath( get_template_directory() . DS . 'views' . DS . 'public' );

		$routeInfo = self::$dispatcher->dispatch( $httpMethod, $uri );

		switch ( $routeInfo[0] ) {
			case \FastRoute\Dispatcher::NOT_FOUND:
				// This will look for Twig files first, and fall back to standard PHP files if
				// no matching Twig file was found.
				$finder = new \Brain\Hierarchy\Finder\FoldersTemplateFinder( [
					$template_dir,
				], [ 'twig' ] );

				$queryTemplate = new \Brain\Hierarchy\QueryTemplate( $finder );

				$path        = $queryTemplate->findTemplate();
				$routeTarget = ! empty( $path ) ? basename( $path, '.twig' ) : '404';
				break;
			case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				break;
			case \FastRoute\Dispatcher::FOUND:
				$routeTarget    = $routeInfo[1];
				$wp->query_vars = $routeInfo[2];
				break;
		}

		/**
		 * if an array was given we assume that an explicit controller and action were given
		 *
		 * otherwise we assume that it's a simple rule and a simple name of a template was given
		 *
		 */
		if ( is_array( $routeTarget ) ) {
			/*
			 * merge with defaults to prevent missing data
			 */
			$routeTarget = array_merge( $this->routeTargetDefaults, $routeTarget );
			/**
			 * instantiate controller and call action
			 */
			$myController = new $routeTarget['controller'];
			if ( ! is_a( $myController, '\Sloth\Controller\Controller' ) ) {
				throw new \ErrorException( 'Controllers must extend \Sloth\Controller\Controller' );
			}
			call_user_func_array( [ $myController, $routeTarget['action'] ], $routeInfo[2] );
		} else {
			$myController = new $this->routeTargetDefaults['controller'];
			call_user_func_array( [ $myController, 'render' ], [ $routeTarget ] );
		}

		#dump( $routeInfo, get_queried_object(), $routeTarget, $wp->query_vars );
	}

	/**
	 * Returns the regex to be registered as a rewrite rule to let WordPress know the existence of this route
	 *
	 * @return mixed|string
	 */
	private function getRewriteRuleRegex( $routeRegex ) {
		if ( preg_match( '/^~/', $routeRegex ) ) {
			// Remove the first part (~^/) of the regex because WordPress adds this already by itself
			$routeRegex = preg_replace( '/^\~\^/', '^', $routeRegex );
			// Remove the last part (\$\~$) of the regex because WordPress adds this already by itself
			$routeRegex = preg_replace( '/\$\~$/', '', $routeRegex );
		} else {
			$routeRegex = preg_replace( '/^\//', '^', $routeRegex );
			$routeRegex = preg_replace( '/\/$/', '', $routeRegex );
			$routeRegex .= '/$';
		}

		return $routeRegex;
	}

	/**
	 * Adds rewrite_tag and rewrite_rule for WordPress to know about the routes
	 *
	 * @TODO: does not seem to work?
	 *
	 */
	public function setRewrite() {
		$regexes = array_unique( $this->regexes );
		foreach ( $regexes as $regex ) {
			add_rewrite_tag( '%is_sloth_route%', '(\d)' );
			add_rewrite_rule( $regex, 'index.php?is_sloth_route=1', 'top' );
		}
		#flush_rewrite_rules( true );
	}
}