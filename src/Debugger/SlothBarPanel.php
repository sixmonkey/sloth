<?php
/**
 * User: Kremer
 * Date: 10.01.18
 * Time: 21:41
 */

namespace Sloth\Debugger;


use Brain\Hierarchy\Hierarchy;
use Sloth\Facades\View;
use Tracy\IBarPanel;

class SlothBarPanel implements IBarPanel {
	function getPanel() {
		global $wp_query;
		$h               = new Hierarchy();
		$currentTemplate = basename( $GLOBALS['sloth::plugin']->getCurrentTemplate(), '.twig' );

		return View::make( 'Debugger.sloth-bar-panel' )->with( [
			'templates'       => $h->getTemplates(),
			'currentTemplate' => $currentTemplate,
		] )->render();
	}

	function getTab() {
		$logo = file_get_contents( dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'logo.svg' );

		return '<span title="SLOTH">' . $logo . '</span>';
	}
}