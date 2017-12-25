<?php
/**
 * User: Kremer
 * Date: 21.12.17
 * Time: 17:27
 */

namespace Sloth\Utility;


class Utility {

	/**
	 * @param $name
	 *
	 * @return bool|mixed|string
	 */
	private static function normalize( $name ) {
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

		$name = \Cake\Utility\Inflector::camelize( str_replace( '-',
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

		$name = \Cake\Utility\Inflector::dasherize( $name );

		return $name;
	}

	/**
	 * @param $name
	 *
	 * @return string
	 */
	public static function acfize( $name ) {
		$name = self::normalize( $name );

		$name = \Cake\Utility\Inflector::underscore( $name );

		return $name;
	}
}