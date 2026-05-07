<?php

declare(strict_types=1);
namespace Sloth\Module\Factory;

use Illuminate\Support\Str;
use InvalidArgumentException;
use Sloth\Module\Module;

/**
 * Factory for instantiating theme modules.
 *
 * Resolves module class names from kebab-case or snake_case names,
 * instantiates them with options, and sets view data.
 *
 * ## Usage
 *
 * Via the container:
 * ```php
 * app('module.factory')->make('hero', ['title' => 'Hello'], ['wrapInRow' => true]);
 * ```
 *
 * Via the module() helper:
 * ```php
 * module('hero', ['title' => 'Hello']);
 * ```
 *
 * ## Class resolution
 *
 * Module names are resolved to class names in the `Theme\Module` namespace:
 * - `hero`         → `Theme\Module\HeroModule`
 * - `hero-section` → `Theme\Module\HeroSectionModule`
 * - `hero_section` → `Theme\Module\HeroSectionModule`
 *
 * @since 1.0.0
 */
class ModuleFactory
{
    /**
     * Instantiate a module by name, set data, and return it ready to render.
     *
     * @param string               $name    module name (kebab-case or snake_case)
     * @param array<string, mixed> $data    key-value pairs to set on the module
     * @param array<string, mixed> $options module constructor options
     *
     * @throws InvalidArgumentException if the module class does not exist
     *
     * @return Module the configured module instance
     *
     * @since 1.0.0
     */
    public function make(string $name, array $data = [], array $options = []): Module
    {
        $class = $this->resolveClass($name);

        if (!class_exists($class)) {
            throw new InvalidArgumentException(
                "Module class [{$class}] not found. "
                . 'Make sure the module exists in Theme\\Module\\.',
            );
        }

        $module = new $class($options);

        foreach ($data as $key => $value) {
            $module->set($key, $value);
        }

        return $module;
    }

    /**
     * Instantiate, configure, and immediately render a module.
     *
     * @param string               $name    module name (kebab-case or snake_case)
     * @param array<string, mixed> $data    key-value pairs to set on the module
     * @param array<string, mixed> $options module constructor options
     *
     * @throws InvalidArgumentException if the module class does not exist
     *
     * @return string the rendered module output
     *
     * @since 1.0.0
     */
    public function render(string $name, array $data = [], array $options = []): string
    {
        return $this->make($name, $data, $options)->render();
    }

    /**
     * Resolve a module name to a fully-qualified class name.
     *
     * @param  string       $name module name (kebab-case or snake_case)
     * @return class-string
     *
     * @since 1.0.0
     */
    public function resolveClass(string $name): string
    {
        return 'Theme\\Module\\' . Str::studly(str_replace('-', '_', $name)) . 'Module';
    }
}
