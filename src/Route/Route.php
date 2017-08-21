<?php

namespace Sloth\Route;

use Brain\Hierarchy\Hierarchy;
use Corcel\Model\Post as Post;

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
				'cacheDisabled' => WP_DEBUG,
			] );
	}

	/**
	 * add a Route to initial collection
	 *
	 * @param array|string httpMethod
	 * @param string $route
	 * @param array $action
	 */
	private static function addRoute( $httpMethod, $route, Array $action ) {
		if ( self::$dispatched ) {
			throw new Exception( 'Adding Routes is no longer possible. Please use your template\'s routes.php to define Routes.' );
		}
		self::$routes[] = [
			'httpMethod' => $httpMethod,
			'route'      => self::normalize( $route ),
			'template'   => $action,
		];
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function add( $route, $action ) {
		self::addRoute( [ 'GET', 'POST' ], $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public function get( $route, $action ) {
		self::addRoute( 'GET', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function post( $route, $action ) {
		self::addRoute( 'POST', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function put( $route, $action ) {
		self::addRoute( 'PUT', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function patch( $route, $action ) {
		self::addRoute( 'PATCH', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function delete( $route, $action ) {
		self::addRoute( 'DELETE', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function head( $route, $action ) {
		self::addRoute( 'HEAD', $route, $action );
	}

	/**
	 * add ad a 'default' Route for GET AND POST
	 *
	 * @param array|string $route
	 * @param string $action
	 */
	public static function any( $route, $action ) {
		self::addRoute( [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD' ], $route, $action );
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

		if ( substr( $route, - 1 ) == ']' ) {
			$route = substr( $route, 0, strlen( $route ) - 1 ) . '[/]]';
		} else {
			$route = rtrim( $route, '/' ) . '[/]';
		}

		return $route;
	}

	/**
	 * dispatch it!
	 */
	public function dispatch() {

		global $wp_query, $wp, $post;

		self::$dispatched = true;

		// Fetch method and URI from $_SERVER
		$httpMethod = $_SERVER['REQUEST_METHOD'];
		$uri        = $_SERVER['REQUEST_URI'];

		// Strip query string (?foo=bar) and decode URI
		if ( false !== $pos = strpos( $uri, '?' ) ) {
			$uri = substr( $uri, 0, $pos );
		}
		$uri = rawurldecode( $uri );

		$routeTarget = [];

		$routeInfo = self::$dispatcher->dispatch( $httpMethod, $uri );

		switch ( $routeInfo[0] ) {
			case \FastRoute\Dispatcher::NOT_FOUND:
				$hierarchy = new Hierarchy();
				$templates = $hierarchy->getTemplates( $wp_query );
				if ( $templates[0] != 404 ) {
					foreach ( $templates as $template ) {
						$myController = $this->getController( $template );
						if ( class_exists( $myController ) ) {
							$routeTarget = [
								'controller' => $myController,
								'action'     => 'index',
							];
							break;
						}
					}
				}

				#$path        = $queryTemplate->findTemplate();
				break;
			case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
				$allowedMethods = $routeInfo[1];
				break;
			case \FastRoute\Dispatcher::FOUND:
				$routeTarget               = $routeInfo[1];
				$routeTarget['controller'] = $this->getController( $routeTarget['controller'] );
				$wp->query_vars            = $routeInfo[2];
				break;
		}

		if ( ! isset( $routeTarget['action'] ) ) {
			$routeTarget['action'] = 'index';
		}

		if ( ! isset( $routeInfo[2] ) ) {
			$routeInfo[2] = [];
		}

		if ( ! isset( $routeTarget['controller'] ) || ! class_exists( $routeTarget['controller'] ) ) {
			# @TODO
			throw new \Exception( '404' );
		}


		$request              = new \stdClass();
		$myPost               = clone $post;
		$myPost->post_content = apply_filters( 'the_content', $myPost->post_content );
		$request->params      = [
			'action' => $routeTarget['action'],
			'pass'   => (array) $routeInfo[2],
			'post'   => $myPost,
		];

		/**
		 * hand current page from wp_query to Illuminate
		 */
		if ( isset( $wp_query->query['page'] ) ) {
			$currentPage = $wp_query->query['page'];
			\Illuminate\Pagination\Paginator::currentPageResolver( function () use ( $currentPage ) {
				return $currentPage;
			} );
		}


		$controller = new $routeTarget['controller'];
		#call_user_func_array( [ $controller, 'invokeAction' ], [ &$request ] );
		#die();
	}

	private function getController( $name ) {
		return 'Theme\Controller\\' . \Cake\Utility\Inflector::camelize( str_replace( '-',
				'_',
				$name ) ) . 'Controller';
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