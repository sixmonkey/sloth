<?php

declare(strict_types=1);

namespace Sloth\Facades;

use Sloth\Core\Application;

/**
 * Facade Base Class
 *
 * Provides a static interface to services registered in the
 * application container. Each facade must define a getFacadeAccessor
 * method that returns the container binding key.
 *
 * @since 1.0.0
 *
 * @example
 * ```php
 * // Define a facade
 * class Route extends Facade {
 *     protected static function getFacadeAccessor(): string {
 *         return 'route';
 *     }
 * }
 *
 * // Use the facade
 * Route::get('/about', ['controller' => 'PageController']);
 * ```
 */
abstract class Facade
{
    /**
     * The Application container instance.
     *
     * @since 1.0.0
     */
    protected static ?Application $app = null;

    /**
     * Sets the application container for all facades.
     *
     * @since 1.0.0
     *
     * @param Application $app The application container
     */
    public static function setFacadeApplication(Application $app): void
    {
        static::$app = $app;
    }

    /**
     * Gets the application container for all facades.
     *
     * @since 1.0.0
     *
     * @return Application|null The application container or null
     */
    public static function getFacadeApplication(): ?Application
    {
        return static::$app;
    }

    /**
     * Gets the instance from the container.
     *
     * @since 1.0.0
     *
     * @return object The resolved service instance
     *
     * @throws \RuntimeException If no application is set
     */
    protected static function getInstance(): object
    {
        $name = static::getFacadeAccessor();

        if (!static::$app instanceof \Sloth\Core\Application) {
            throw new \RuntimeException('Facade application not set.');
        }

        return static::$app[$name];
    }

    /**
     * Returns the container binding key for this facade.
     *
     * Each facade must implement this method to return the
     * key that the service is bound to in the container.
     *
     * @since 1.0.0
     *
     * @return string The container binding key
     *
     * @throws \RuntimeException Always - subclasses must implement this
     */
    protected static function getFacadeAccessor(): string
    {
        throw new \RuntimeException(
            'Facade does not implement the "getFacadeAccessor" method.'
        );
    }

    /**
     * Magic method for static calls.
     *
     * Handles dynamic method calls by forwarding them to the
     * resolved service instance.
     *
     * @since 1.0.0
     *
     * @param string $method The method name to call
     * @param array<int, mixed> $args The arguments to pass
     *
     * @return mixed The result of the method call
     */
    public static function __callStatic(string $method, array $args): mixed
    {
        $instance = static::getInstance();

        return call_user_func_array([$instance, $method], $args);
    }
}
