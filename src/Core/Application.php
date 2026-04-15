<?php

declare(strict_types=1);

namespace Sloth\Core;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Str;

/**
 * Application Container
 *
 * This is the main application container for the Sloth framework.
 * It extends Laravel's Container to provide dependency injection
 * and service provider registration.
 *
 * @since 1.0.0
 * @see \Illuminate\Container\Container
 */
class Application extends Container
{
    /**
     * Project paths mapped by key.
     * These are stored in the container as 'path.{key}'.
     *
     * @since 1.0.0
     * @var array<string, string>
     */
    protected array $paths = [];

    /**
     * Registry of loaded service providers.
     * Maps provider class name to boolean (always true when loaded).
     *
     * @since 1.0.0
     * @var array<string, bool>
     */
    protected array $loadedProviders = [];

    /**
     * Creates a new Application instance.
     *
     * Initializes the application container and registers itself
     * into the container so it can be accessed from anywhere.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->registerApplication();
    }

    /**
     * Registers the Application class into the container.
     *
     * This allows the application instance to be accessed from
     * the container itself via dependency injection or the
     * static setInstance method.
     *
     * @since 1.0.0
     * @see Application::getInstance()
     */
    public function registerApplication(): void
    {
        static::setInstance($this);
        $this->instance('app', $this);
    }

    /**
     * Registers a service provider with the application.
     *
     * If the provider hasn't been loaded yet, it will be instantiated
     * (if a string class name was provided), registered, and then booted.
     *
     * @since 1.0.0
     *
     * @param ServiceProvider|string $provider The service provider instance or class name
     * @param array<string, mixed> $options Optional configuration options
     * @param bool $force Force registration even if already loaded
     *
     * @return ServiceProvider The registered service provider
     *
     * @throws \Exception If provider registration fails
     */
    public function register($provider, array $options = [], bool $force = false): ServiceProvider
    {
        if (!$provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        $providerName = $provider::class;

        if (array_key_exists($providerName, $this->loadedProviders) && !$force) {
            return $provider;
        }

        $this->loadedProviders[$providerName] = true;
        $provider->register();

        if (method_exists($provider, 'boot')) {
            $provider->boot();
        }

        return $provider;
    }

    /**
     * Adds a file path to the container.
     *
     * Paths are stored in the container with the key prefixed by 'path.'.
     * For example, 'cache' becomes 'path.cache'.
     *
     * @since 1.0.0
     *
     * @param string $key The path identifier (e.g., 'cache', 'views')
     * @param string $path The full filesystem path
     *
     * @example $app->addPath('cache', '/var/www/cache');
     */
    public function addPath(string $key, string $path): void
    {
        $this->instance('path.' . $key, $path);
    }

    /**
     * Calls a theme module with the given data and options.
     *
     * Modules are stored in the Theme\Module namespace and must follow
     * the naming convention: '{Name}Module' where {Name} is the camel-cased
     * module name.
     *
     * @since 1.0.0
     *
     * @param string $name The module name (kebab-case or snake_case)
     * @param array<string, mixed> $data Key-value pairs to set on the module
     * @param array<string, mixed> $options Module configuration options
     *
     * @return string The rendered module output
     *
     * @throws \Exception If the module class doesn't exist
     *
     * @example $app->callModule('my-module', ['title' => 'Hello'], ['theme' => 'dark']);
     */
    public function callModule(string $name, array $data = [], array $options = []): string
    {
        $moduleName = 'Theme\\Module\\' . Str::camel(str_replace('-', '_', $name)) . 'Module';
        $myModule = new $moduleName($options);

        foreach ($data as $k => $v) {
            $myModule->set($k, $v);
        }

        return $myModule->render();
    }

    /**
     * Resolves the given type from the container with custom handling.
     *
     * This method provides custom resolution for specific classes like the Paginator.
     *
     * @param class-string|object $abstract The class or interface to resolve
     * @param array<string, mixed> $parameters Constructor parameters
     * @param bool $raiseEvents Whether to fire resolution events
     *
     * @return mixed The resolved instance
     *
     * @throws BindingResolutionException
     * @since 1.1.0 Renamed from resolve to avoid conflict with protected parent method
     *
     * @see \Illuminate\Container\Container::make()
     * @since 1.0.0
     */
    public function resolveFromContainer($abstract, array $parameters = [], $raiseEvents = true): mixed
    {
        if ($abstract === \Illuminate\Pagination\LengthAwarePaginator::class) {
            $abstract = \Sloth\Pagination\Paginator::class;
        }

        return $this->make($abstract, $parameters);
    }
}
