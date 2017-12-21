<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 17:27
 */

namespace Sloth\Utility;


class Utility {
	public static function modulize( $name ) {
		$name = preg_replace( '/Module$/',
			'',
			substr( strrchr( $name, "\\" ), 1 ) );
		$name = 'Theme\Module\\' . \Cake\Utility\Inflector::camelize( str_replace( '-',
				'_',
				$name ) ) . 'Module';

		return $name;
	}
}