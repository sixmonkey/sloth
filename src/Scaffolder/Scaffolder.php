<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 15:32
 */

namespace Sloth\Scaffolder;

use League\CLImate\CLImate;
use Sloth\Facades\View;
use Sloth\Utility\Utility;
use gossi\docblock\Docblock;
use Spatie\Emoji\Emoji;


class Scaffolder {
	private $climate;
	private $composer_config_location;
	private $composer_config;

	public function __construct() {
		$this->climate = new CLImate;
		$this->climate->addArt( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'Climate' );
		$this->climate->green()->animation( 'logo' )->speed( 400 )->enterFrom( 'left' );

		$this->composer_config_location = self::_get_composer_json_location();
		$this->composer_config          = json_decode( file_get_contents( $this->composer_config_location . 'composer.json' ),
			true );

	}

	/**
	 * Create a module with all necessary files
	 *
	 * @throws \Exception
	 */
	public function create_module() {
		$context = [];
		$this->climate->green()->out( 'Creating new Module…' );

		$context['name'] = null;
		$filename_module = null;
		while ( $context['name'] == null || file_exists( $filename_module ) ) {
			$input           = $this->climate->green()->input( "What will your Module be called?\r\n>" );
			$context['name'] = trim( $input->prompt() );

			if ( $context['name'] == null ) {
				$this->climate->error( Emoji::pileOfPoo() . ' Please give a name for this Module!' );
				continue;
			}

			$context['name']      = Utility::modulize( $context['name'] );
			$context['id']        = strtolower( Utility::normalize( $context['name'] ) );
			$context['name_view'] = Utility::viewize( $context['name'] );
			$context['name_acf']  = Utility::acfize( $context['name'] );

			$filename_module = $GLOBALS['sloth']->container->get( 'path.theme' ) . DS . 'Module' . DS . $context['name'] . '.php';
			if ( file_exists( $filename_module ) ) {
				$this->climate->error( Emoji::pileOfPoo() . ' ' . sprintf( 'A module called %s exists!',
						$context['name'] ) );
			}
		}

		$input               = $this->climate->green()->confirm( 'Do you want to use your module with Layotter?' );
		$context['layotter'] = $input->confirmed() ? [] : false;

		if ( is_array( $context['layotter'] ) ) {
			$input                        = $this->climate->green()->input( "Please give a comprehensive title for your module.\r\n>" );
			$context['layotter']['title'] = trim( $input->prompt() );

			$input                              = $this->climate->green()->input( "Please give a short description of what this module will do.\r\n>" );
			$context['layotter']['description'] = trim( $input->prompt() );

			$input                       = $this->climate->green()->input( "Please choose an icon for this module.\r\n(choose from http://fontawesome.io/icons/)\r\n>" );
			$context['layotter']['icon'] = trim( $input->prompt() );
		}
		/*
				$input           = $this->climate->green()->confirm( 'Do you want me to create a sass file for this module?' );
				$context['sass'] = $input->confirmed();

				$input         = $this->climate->green()->confirm( 'Do you want me to create a JavaScript file for this module?' );
				$context['js'] = $input->confirmed();
		*/

		# write it to file
		$view = View::make( 'Scaffold.Module.Class' );
		file_put_contents( $filename_module, $view->with( $context )->render() );

		$filename_view = $GLOBALS['sloth']->container->get( 'path.theme' ) . DS . 'View' . DS . 'Module' . DS . $context['name_view'] . '.twig';
		$view          = View::make( 'Scaffold.Module.View' );
		file_put_contents( $filename_view, $view->with( $context )->render() );


		$sass_dir = DIR_ROOT . DS . 'src' . DS . 'sass' . DS;
		if ( ! is_dir( $sass_dir ) ) {
			$sass_dir = DIR_ROOT . DS . 'src' . DS . 'scss' . DS;
		}

		$filename_sass = $sass_dir . 'modules' . DS . '_' . $context['name_view'] . '.scss';
		if ( ! is_dir( dirname( $filename_sass ) ) ) {
			mkdir( dirname( $filename_sass ), 0777, true );
		}
		$view = View::make( 'Scaffold.Module.Sass' );
		file_put_contents( $filename_sass, $view->with( $context )->render() );


		$filename_sass_bundle = $sass_dir . 'bundle.scss';
		file_put_contents( $filename_sass_bundle,
			sprintf( "\n@import 'modules/%s';", $context['name_view'] ),
			FILE_APPEND );

		if ( $context['layotter'] ) {
			$filename_acf = $GLOBALS['sloth']->container->get( 'path.theme' ) . DS . 'acf-json' . DS . $context['name_acf'] . '.json';
			if ( file_exists( $filename_acf ) ) {
				$this->climate->info( Emoji::thinkingFace() . ' ' . sprintf( 'A Field Group %s exists. Skipping scaffolding!',
						$context['name_acf'] ) );
			} else {
				$view = View::make( 'Scaffold.Module.Acf' );
				$data = json_decode( $view->with( $context + [ 'now' => time() ] )->render() );
				if ( json_last_error() != JSON_ERROR_NONE ) {
					throw new \Exception( 'Seems your scaffold is not correct json!' );
				}
				file_put_contents( $filename_acf, json_encode( $data ) );
			}
		}

		$this->climate->info( sprintf( 'Module %s created!', $context['name'] ) );
	}

	/**
	 * @return string
	 *
	 * @throws \UnexpectedValueException
	 */
	private static function _get_composer_json_location(): string {
		// bold assumption, but there's not here to fix everyone's problems.
		$checkedPaths = [ __DIR__ . '/../../../../../composer.json', __DIR__ . '/../../composer.json' ];
		foreach ( $checkedPaths as $path ) {
			if ( file_exists( $path ) ) {
				return realpath( dirname( $path ) ) . DIRECTORY_SEPARATOR;
			}
		}
		throw new \UnexpectedValueException( sprintf(
			'PackageVersions could not locate your `composer.lock` location. This is assumed to be in %s. '
			. 'If you customized your composer vendor directory and ran composer installation with --no-scripts, '
			. 'then you are on your own, and we can\'t really help you. Fix your shit and cut the tooling some slack.',
			json_encode( $checkedPaths )
		) );
	}

	/**
	 * guess what!
	 */
	public function help() {
		$this->climate->green( "Sloth CLI currently supports the following commands:\r\n" );
		$r = new \ReflectionClass( __CLASS__ );

		$methods = $r->getMethods( \ReflectionMethod::IS_PUBLIC );
		foreach ( $methods as $method ) {
			if ( preg_match( '/^_/', $method->name ) ) {
				continue;
			}
			$docblock = new Docblock( $r->getMethod( $method->name )->getDocComment() );
			$this->climate->green()->bold( $method->name )->tab()->green( $docblock->getShortDescription() );
		}
	}

	/**
	 * @return string
	 */
	public function _get_wordpress_install_dir() {
		return $this->composer_config_location . $this->composer_config['extra']['wordpress-install-dir'] . DIRECTORY_SEPARATOR;
	}
}