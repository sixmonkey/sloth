<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 17:27
 */

namespace Sloth\Utility;

use \Cake\Utility\Inflector;

class Utility extends Inflector {

	/**
	 * @param $name
	 *
	 * @return bool|mixed|string
	 */
	public static function normalize( $name ) {
		if ( strstr( '\\', $name ) ) {
			$name = substr( strrchr( $name, "\\" ), 1 );
		}

		$name = preg_replace( '/Module$/',
			'',
			$name );

		return $name;
	}

	/**
	 * return a module name
	 *
	 * @param      $name
	 * @param bool $namespaced
	 *
	 * @return string
	 */
	public static function modulize( $name, $namespaced = false ) {
		$name = self::normalize( $name );

		$name = self::camelize( str_replace( '-',
				'_',
				$name ) ) . 'Module';

		if ( $namespaced ) {
			$name = 'Theme\Module\\' . $name;
		}

		return $name;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	public static function viewize( $name ) {
		$name = self::normalize( $name );

		$name = self::dasherize( $name );

		return $name;
	}

	/**
	 * @param $name
	 * @param $prefixed
	 *
	 * @return string
	 */
	public static function acfize( $name, $prefixed = true ) {
		$name = self::normalize( $name );

		$name = self::underscore( $name );
		if ( $prefixed ) {
			$name = 'group_module_' . $name;
		}

		return $name;
	}
}