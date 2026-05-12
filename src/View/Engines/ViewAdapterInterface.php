<?php

declare(strict_types=1);
namespace Sloth\View\Engines;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\View\Factory;

/**
 * Interface for view engine adapters.
 *
 * Each adapter is responsible for registering a template engine
 * (Twig, Blade, etc.) with the View Factory, and for consuming
 * the engine-agnostic helpers, directives and shared variables
 * collected by ViewServiceProvider.
 *
 * @since 1.0.0
 */
interface ViewAdapterInterface
{
    /**
     * Register engine-specific container bindings.
     *
     * Called during ViewServiceProvider::register() — do not resolve
     * other services here as they may not be bound yet.
     *
     * @since 1.0.0
     *
     * @param Application $app
     */
    public function register(Application $app): void;

    /**
     * Boot the engine — register helpers, directives and globals.
     *
     * Called during ViewServiceProvider::boot() — after all extensions
     * have been discovered and _helpers/_directives/_shared are set
     * on the View Factory.
     *
     * @since 1.0.0
     *
     * @param Factory     $view
     * @param Application $app
     */
    public function boot(Factory $view, Application $app): void;
}
