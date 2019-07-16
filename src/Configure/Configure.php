<?php
/**
 * User: Kremer
 * Date: 28.12.17
 * Time: 14:42
 */

namespace Sloth\Configure;

use Cake\Utility\Hash;


class Configure extends \Singleton {
    /**
     * Array of values currently stored in Configure.
     *
     * @var array
     */
    protected static $_values = [
        'debug' => 0,
    ];

    /**
     * Used to store a dynamic variable in Configure.
     *
     * Usage:
     * ```
     * Configure::write('One.key1', 'value of the Configure::One[key1]');
     * Configure::write(array('One.key1' => 'value of the Configure::One[key1]'));
     * Configure::write('One', array(
     *     'key1' => 'value of the Configure::One[key1]',
     *     'key2' => 'value of the Configure::One[key2]'
     * );
     *
     * Configure::write(array(
     *     'One.key1' => 'value of the Configure::One[key1]',
     *     'One.key2' => 'value of the Configure::One[key2]'
     * ));
     * ```
     *
     * @param string|array $config The key to write, can be a dot notation value.
     *                             Alternatively can be an array containing key(s) and value(s).
     * @param mixed        $value  Value to set for var
     *
     * @return bool True if write was successful
     */
    public static function write( $config, $value = null ) {
        if ( ! is_array( $config ) ) {
            $config = [ $config => $value ];
        }
        foreach ( $config as $name => $value ) {
            static::$_values = Hash::insert( static::$_values, $name, $value );
        }

        return true;
    }

    /**
     * Used to read information stored in Configure. It's not
     * possible to store `null` values in Configure.
     *
     * Usage:
     * ```
     * Configure::read('Name'); will return all values for Name
     * Configure::read('Name.key'); will return only the value of Configure::Name[key]
     * ```
     *
     * @param string|null $var Variable to obtain. Use '.' to access array elements.
     *
     * @return mixed value stored in configure, or null.
     */
    public static function read( $var = null ) {
        if ( $var === null ) {
            return static::$_values;
        }

        return Hash::get( static::$_values, $var );
    }

    /**
     * Used to read and delete a variable from Configure.
     *
     * This is primarily used during bootstrapping to move configuration data
     * out of configure into the various other classes in CakePHP.
     *
     * @param string $var The key to read and remove.
     *
     * @return array|null
     */
    public static function consume( $var ) {
        $simple = strpos( $var, '.' ) === false;
        if ( $simple && ! isset( static::$_values[ $var ] ) ) {
            return null;
        }
        if ( $simple ) {
            $value = static::$_values[ $var ];
            unset( static::$_values[ $var ] );

            return $value;
        }
        $value           = Hash::get( static::$_values, $var );
        static::$_values = Hash::remove( static::$_values, $var );

        return $value;
    }

    /**
     * Returns true if given variable is set in Configure.
     *
     * @param string $var Variable name to check for
     *
     * @return bool True if variable is there
     */
    public static function check( $var ) {
        if ( empty( $var ) ) {
            return false;
        }

        return Hash::get( static::$_values, $var ) !== null;
    }

    /**
     * Used to delete a variable from Configure.
     *
     * Usage:
     * ```
     * Configure::delete('Name'); will delete the entire Configure::Name
     * Configure::delete('Name.key'); will delete only the Configure::Name[key]
     * ```
     *
     * @param string $var the var to be deleted
     *
     * @return void
     */
    public static function delete( $var ) {
        static::$_values = Hash::remove( static::$_values, $var );

    }

    /**
     * Copy anything from env to Configure
     */
    public static function boot() {
        foreach ( $_ENV as $k => $v ) {
            self::write( 'ENV.' . $k, $v );
        }
    }

    /**
     * Debug all set variables
     */
    public static function debug() {
        debug( self::$_values );
    }
}
