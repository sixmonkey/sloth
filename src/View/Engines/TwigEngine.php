<?php

namespace Sloth\View\Engines;

use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\ViewFinderInterface;
use Twig_Environment;

class TwigEngine extends PhpEngine {
	/**
	 * @var Twig_Environment
	 */
	protected $environment;

	/**
	 * @var \Illuminate\View\ViewFinderInterface
	 */
	protected $finder;

	/**
	 * @var string
	 */
	protected $extension = '.twig';

	public function __construct( Twig_Environment $environment, ViewFinderInterface $finder ) {
		$this->environment = $environment;
		$this->finder      = $finder;
	}

	/**
	 * Return the evaluated template.
	 *
	 * @param string $path The file name with its file extension.
	 * @param array $data Template data (view data)
	 *
	 * @return string
	 */
	public function get( $path, array $data = [] ) {
		/**
		 * get path relative from WWW_ROOT
		 */
		$path = ( preg_replace( '~^' . DIR_WWW . '~', '', $path ) );
		return $this->environment->render( $path, $data );
	}
}
