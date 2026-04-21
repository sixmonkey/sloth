<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;

/**
 * Base Service Provider
 *
 * This is the abstract base class for all Sloth service providers.
 * It extends Laravel's ServiceProvider and provides a common
 * error handling mechanism for undefined method calls.
 *
 * All framework service providers should extend this class.
 *
 * @since 1.0.0
 * @see IlluminateServiceProvider For the base Laravel implementation
 *
 * @example
 * ```php
 * class MyServiceProvider extends ServiceProvider
 * {
 *     public function register(): void
 *     {
 *         // Register services
 *     }
 *
 *     public function boot(): void
 *     {
 *         // Boot services
 *     }
 * }
 * ```
 */
abstract class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Returns WordPress actions this provider wants to register.
     * The framework calls add_action() for each entry — never call
     * add_action() directly inside a provider.
     *
     * Supports three formats per hook:
     * - Single callable:   'init' => fn() => $this->doSomething()
     * - Array of callables: 'init' => [fn() => ..., fn() => ...]
     * - With priority:     'init' => ['callback' => fn() => ..., 'priority' => 20]
     *
     * @return array<string, callable|array>
     */
    public function getHooks(): array
    {
        return [];
    }

    /**
     * Returns WordPress filters this provider wants to register.
     * Same format as getHooks().
     *
     * @return array<string, callable|array>
     */
    public function getFilters(): array
    {
        return [];
    }

    /**
     * Handles calls to undefined methods.
     *
     * This magic method catches any calls to undefined methods.
     * The 'boot' method is silently ignored to allow for lazy booting.
     * All other undefined method calls throw an exception.
     *
     * @since 1.0.0
     *
     * @param string $method The method name that was called
     * @param array<int, mixed> $parameters The parameters passed to the method
     *
     * @return mixed|null Returns null for boot(), or throws exception
     *
     * @throws \Exception If the method is not 'boot' and is not defined
     *
     * @example
     * ```php
     * // Silently ignored (boot is optional)
     * $provider->optionalBoot();
     *
     * // Throws exception
     * $provider->undefinedMethod();
     * ```
     */
    public function __call(string $method, array $parameters): mixed
    {
        if ($method === 'boot') {
            return null;
        }

        throw new \Exception(sprintf(
            'Call to undefined method [%s::%s]',
            static::class,
            $method
        ));
    }
}
