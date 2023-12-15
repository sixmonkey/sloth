<?php

/**
 * Class Singleton
 *
 * a simple implementation for Singleton classes
 * Classes that extend this calss, can be used as a Singleton
 *
 */
namespace Sloth\Singleton;

class Singleton
{
    private static $instances = [];

    /**
     * Singleton constructor.
     *
     * protected so it can't be called outside of the class
     */
    protected function __construct() {}

    /**
     * Singleton clone method
     *
     * protected so it can't be called outside of the class
     */
    protected function __clone() {}

    /**
     * Singleton wakeup (unserialize) method
     *
     * protected so it can't be called outside of the class
     */
    public function __wakeup() {}

    /**
     * return an instance of the called class
     *
     * @return mixed
     */
    public static function getInstance() {

        // late-static-bound class name
        $classname = get_called_class();
        if (!isset(self::$instances[$classname])) {
            self::$instances[$classname] = new static;
        }
        return self::$instances[$classname];
    }
}