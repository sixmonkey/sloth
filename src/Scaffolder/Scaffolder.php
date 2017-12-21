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

	public function __construct() {
		$this->climate = new CLImate;
		$this->climate->addArt( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'Climate' );
		$this->climate->green()->animation( 'logo' )->speed(400)->enterFrom('left');
	}

	public function create_module() {
		$this->climate->green()->out( 'Creating new Module…' );

		$name = null;
		while ( $name == null ) {
			$input = $this->climate->green()->input( 'What will your Module be called?' );
			$name  = $input->prompt();
		}
		$name = Utility::modulize($name);

		#$this->climate->error(sprintf('A module %s exists.', $name));
		var_dump($name);

		$input    = $this->climate->green()->confirm( 'Do you want to use your module with Layotter?' );
		$layotter = $input->confirmed();

		$input    = $this->climate->green()->confirm( 'Do you want me to create a sass file for this module?' );
		$sass = $input->confirmed();

		$input    = $this->climate->green()->confirm( 'Do you want me to create a JavaScript file for this module?' );
		$js = $input->confirmed();
	}
}