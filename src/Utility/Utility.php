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
     * @return string
     */
    public static function normalize( $name ) {
        if ( strstr( $name, '\\' ) ) {
            $name = substr( strrchr( $name, "\\" ), 1 );
        }

        $name = preg_replace( '/Module$/',
            '',
            $name );

        $name = str_replace( ' ',
            '-',
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

    /**
     * make a float to a fraction
     *
     * @param       $n
     * @param float $tolerance
     *
     * @return string
     */
    public static function float2fraction( $n, $tolerance = 1.e-6 ) {
        $h1 = 1;
        $h2 = 0;
        $k1 = 0;
        $k2 = 1;
        $b  = 1 / $n;
        do {
            $b   = 1 / $b;
            $a   = floor( $b );
            $aux = $h1;
            $h1  = $a * $h1 + $h2;
            $h2  = $aux;
            $aux = $k1;
            $k1  = $a * $k1 + $k2;
            $k2  = $aux;
            $b   = $b - $a;
        } while ( abs( $n - $h1 / $k1 ) > $n * $tolerance );

        return "$h1/$k1";
    }
}
