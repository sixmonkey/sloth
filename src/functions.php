<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Facades\Facade;
use Sloth\Routing\UrlGenerator;

if (!function_exists('debug')) {
    /**
     * Dumps variables to PHP Debug-Bar bar for debugging.
     *
     * @param  mixed ...$vars Variables to dump
     * @return mixed Returns the first variable unchanged
     */
    function debug(mixed ...$vars): mixed
    {
        if (function_exists('dump')) {
            return dump(...$vars);
        }

        var_dump(...$vars);

        return $vars[0] ?? null;
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value.
     *
     * @param array|string|null $key
     * @param mixed             $default
     *
     * @throws BindingResolutionException
     */
    function config($key = null, $default = null): mixed
    {
        $app = Facade::getFacadeApplication();

        if ($app instanceof Sloth\Core\Application && $app->bound('config')) {
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
     * @param mixed                $abstract
     * @param array<string, mixed> $parameters
     *
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
     * @param string               $name    module name (kebab-case or snake_case)
     * @param array<string, mixed> $data    key-value pairs passed to the module
     * @param array<string, mixed> $options constructor options for the module
     *
     * @throws BindingResolutionException|InvalidArgumentException if the module class does not exist
     *
     * @return string the rendered module HTML
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

if (!function_exists('url')) {
    /**
     * Generate a URL using the UrlGenerator.
     *
     * Returns the UrlGenerator instance when called without arguments,
     * or generates a URL for the given path.
     *
     * ```php
     * url()                    // UrlGenerator instance
     * url('/about')            // https://example.com/about
     * url()->theme('css/app')  // https://example.com/.../theme/css/app
     * url()->route('post.show', ['slug' => 'hello'])
     * ```
     *
     * @param string|null $path optional path to append to the home URL
     *
     * @throws BindingResolutionException
     *
     * @return string|UrlGenerator
     *
     * @since 1.0.0
     */
    function url(?string $path = null): UrlGenerator|string
    {
        $generator = app('url');

        if ($path === null) {
            return $generator;
        }

        return $generator->to($path);
    }
}
