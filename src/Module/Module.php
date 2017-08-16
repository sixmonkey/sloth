<?php
/**
 * User: Kremer
 * Date: 16.08.17
 * Time: 00:34
 */

namespace Sloth\Module;

if ( class_exists( '\Layotter_Element' ) ) {
	class Module extends \Layotter_Element {
		use BaseModule;
	}
} else {
	class Module {
		use BaseModule;
	}
}