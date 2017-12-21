<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 15:32
 */

namespace Sloth\Scaffolder;



class Scaffolder {
	private $climate;

	public function __construct() {
		$this->climate = new CLImate;
	}

	public function create_module() {
	}
}