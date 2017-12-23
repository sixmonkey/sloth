<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 15:32
 */

namespace Sloth\Scaffolder;

use League\CLImate\CLImate;
use Sloth\Utility\Utility;

class Scaffolder {
	private $climate;
	private static $composer_config_location;
	private static $composer_config;
	private static $can_wp = false;

	public function __construct() {
		$this->climate = new CLImate;
		$this->climate->addArt( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'Climate' );
		$this->climate->green()->animation( 'logo' )->speed( 400 )->enterFrom( 'left' );

		self::$composer_config_location = self::get_composer_json_location();
		self::$composer_config          = json_decode( file_get_contents( self::$composer_config_location . 'composer.json' ),
			true );
		self::$can_wp                   = ( include( self::$composer_config_location . self::$composer_config['extra']['wordpress-install-dir'] . DIRECTORY_SEPARATOR . 'wp-blog-header.php' ) );
		var_dump(self::$composer_config_location . self::$composer_config['extra']['wordpress-install-dir'] . DIRECTORY_SEPARATOR . 'wp-load.php');
	}

	public function create_module() {
		$this->climate->green()->out( 'Creating new Module…' );

		$name = null;
		while ( $name == null ) {
			$input = $this->climate->green()->input( 'What will your Module be called?' );
			$name  = $input->prompt();
		}
		$name      = Utility::modulize( $name );
		$name_view = Utility::viewize( $name );
		$name_acf  = Utility::acfize( $name );

		#$this->climate->error(sprintf('A module %s exists.', $name))

		$input    = $this->climate->green()->confirm( 'Do you want to use your module with Layotter?' );
		$layotter = $input->confirmed();

		$input = $this->climate->green()->confirm( 'Do you want me to create a sass file for this module?' );
		$sass  = $input->confirmed();

		$input = $this->climate->green()->confirm( 'Do you want me to create a JavaScript file for this module?' );
		$js    = $input->confirmed();
	}

	/**
	 * @return string
	 *
	 * @throws \UnexpectedValueException
	 */
	private static function get_composer_json_location(): string {
		// bold assumption, but there's not here to fix everyone's problems.
		$checkedPaths = [ __DIR__ . '/../../../../../composer.json', __DIR__ . '/../../composer.json' ];
		foreach ( $checkedPaths as $path ) {
			if ( file_exists( $path ) ) {
				return dirname( $path ) . DIRECTORY_SEPARATOR;
			}
		}
		throw new \UnexpectedValueException( sprintf(
			'PackageVersions could not locate your `composer.lock` location. This is assumed to be in %s. '
			. 'If you customized your composer vendor directory and ran composer installation with --no-scripts, '
			. 'then you are on your own, and we can\'t really help you. Fix your shit and cut the tooling some slack.',
			json_encode( $checkedPaths )
		) );
	}
}