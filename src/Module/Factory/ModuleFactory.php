<?php

declare(strict_types=1);

namespace Sloth\Module\Factory;

use Illuminate\Support\Str;
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
     * @param string $name Module name (kebab-case or snake_case).
     * @param array<string, mixed> $data Key-value pairs to set on the module.
     * @param array<string, mixed> $options Module constructor options.
     * @return Module The configured module instance.
     * @throws \InvalidArgumentException If the module class does not exist.
     * @since 1.0.0
     */
    public function make(string $name, array $data = [], array $options = []): Module
    {
        $class = $this->resolveClass($name);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(
                "Module class [{$class}] not found. " .
                "Make sure the module exists in Theme\\Module\\."
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
     * @param string $name Module name (kebab-case or snake_case).
     * @param array<string, mixed> $data Key-value pairs to set on the module.
     * @param array<string, mixed> $options Module constructor options.
     * @return string The rendered module output.
     * @throws \InvalidArgumentException If the module class does not exist.
     * @since 1.0.0
     */
    public function render(string $name, array $data = [], array $options = []): string
    {
        return $this->make($name, $data, $options)->render();
    }

    /**
     * Resolve a module name to a fully-qualified class name.
     *
     * @param string $name Module name (kebab-case or snake_case).
     * @return class-string
     * @since 1.0.0
     */
    public function resolveClass(string $name): string
    {
        return 'Theme\\Module\\' . Str::studly(str_replace('-', '_', $name)) . 'Module';
    }
}
