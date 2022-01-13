<?php

namespace Sloth\Facades;

abstract class Facade
{
    /**
     * The Application instance.
     *
     * @var \Sloth\Core\Application
     */
    protected static $app;

    /**
     * Magic method. Use to dynamically call the registered
     * instance method.
     *
     * @param string $method The class method used.
     * @param array  $args   The method arguments.
     *
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getInstance();

        /*
         * Call the instance and its method.
         */

        return call_user_func_array([$instance, $method], $args);
    }

    /**
     * Retrieve an instance from the container based on the
     * alias defined in the facade.
     *
     * @return mixed
     */
    public static function getInstance()
    {
        $name = static::getFacadeAccessor();

        return static::$app[ $name ];
    }

    /**
     * Set the service container for the facades.
     *
     * @param \Sloth\Core\Application $app
     */
    public static function setFacadeApplication($app)
    {
        static::$app = $app;
    }

    /**
     * Each facade must define their igniter service
     * class key name.
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        throw new \RuntimeException('Facade does not implement the "getFacadeAccessor" method.');
    }
}
