<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Facades\Facade;
use Tracy\Debugger;

if (!function_exists('debug')) {
    /**
     * Dumps variables to Tracy bar for debugging.
     *
     * @param mixed ...$vars Variables to dump
     *
     * @return mixed Returns the first variable unchanged
     */
    function debug(mixed ...$vars): mixed
    {
        if (class_exists(Debugger::class)) {
            foreach ($vars as $var) {
                Debugger::barDump($var);
            }
        }

        return $vars[0] ?? null;
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param array|string|null $key
     * @param mixed $default
     * @return mixed
     * @throws BindingResolutionException
     */
    function config($key = null, $default = null): mixed
    {
        $app = Facade::getFacadeApplication();
        if ($app !== null && $app->bound('config')) {
            /** @var Repository $repository */
            $repository = $app->make('config');
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $repository->set($k, $v);
                }
                return true;
            }
            return $repository->get($key, $default);
        }
        return $default;
    }
}

if (!function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param null $abstract
     * @param array $parameters
     * @return mixed
     * @throws BindingResolutionException
     */
    function app($abstract = null, array $parameters = []): mixed
    {
        if (is_null($abstract)) {
            return Container::getInstance();
        }
        return Container::getInstance()->make($abstract, $parameters);
    }
}

if (!function_exists('module')) {
    /**
     * Instantiate and render a theme module.
     *
     * Thin wrapper around app('module.factory')->render().
     *
     * @param string $name Module name (kebab-case or snake_case).
     * @param array<string, mixed> $data Key-value pairs passed to the module.
     * @param array<string, mixed> $options Constructor options for the module.
     * @return string The rendered module HTML.
     * @throws \InvalidArgumentException|BindingResolutionException If the module class does not exist.
     *
     * @example
     * ```php
     * // In a Twig template or PHP view:
     * echo module('hero', ['title' => 'Hello World']);
     * echo module('hero-section', ['posts' => $posts], ['wrapInRow' => true]);
     * ```
     */
    function module(string $name, array $data = [], array $options = []): string
    {
        return app('module.factory')->render($name, $data, $options);
    }
}
