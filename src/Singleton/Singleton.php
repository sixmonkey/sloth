<?php

declare(strict_types=1);

namespace Sloth\Singleton;

/**
 * Singleton class for ensuring only one instance exists.
 *
 * Classes that extend this class can be used as a Singleton.
 *
 * @since 1.0.0
 */
class Singleton
{
    /**
     * Storage for singleton instances.
     *
     * @since 1.0.0
     * @var array<string, static>
     */
    private static array $instances = [];

    /**
     * Singleton constructor.
     *
     * Protected so it can't be called outside of the class.
     *
     * @since 1.0.0
     */
    protected function __construct() {}

    /**
     * Get the singleton instance.
     *
     * @since 1.0.0
     */
    public static function getInstance(): static
    {
        $className = static::class;

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new static();
        }

        return self::$instances[$className];
    }

    /**
     * Prevent singleton instance from being unserialized.
     *
     * @since 1.0.0
     *
     *
     * @throws \RuntimeException Always throws exception
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }
}
