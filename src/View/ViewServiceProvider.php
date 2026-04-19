<?php

declare(strict_types=1);

namespace Sloth\View;

use Illuminate\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Sloth\Core\ServiceProvider;
use Sloth\View\Engines\TwigEngine;
use Sloth\View\Extensions\SlothTwigExtension;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

/**
 * Service provider for the View rendering component.
 *
 * ## Boot sequence
 *
 * register() only binds singletons — it does NOT resolve them.
 * Resolving a singleton during register() can cause infinite loops
 * if the singleton's closure calls config() or other container bindings
 * that are still being built.
 *
 * Twig extensions are added in boot() — after all providers have
 * registered their services and the container is fully built.
 *
 * @since 1.0.0
 */
class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register View services.
     *
     * Binds singletons only — does not resolve any of them.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->registerTwigLoader();
        $this->registerTwigEnvironment();
        $this->registerEngineResolver();
        $this->registerViewFactory();
    }

    /**
     * Boot View services.
     *
     * Adds Twig extensions after all providers are registered and
     * the container is fully built. Safe to resolve singletons here.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        $twig = $this->app['twig'];

        $twig->addExtension(new DebugExtension());
        $twig->addExtension(new SlothTwigExtension($this->app));

        if (defined('WP_DEBUG') && WP_DEBUG) {
            $twig->enableDebug();
        }

        // Theme filters and functions registered via config
        // are picked up by SlothTwigExtension::getFilters/getFunctions()
    }

    /**
     * Register the Twig filesystem loader.
     *
     * Starts with an empty paths array — ThemeServiceProvider adds
     * theme and framework view paths after providers are registered.
     *
     * @since 1.0.0
     */
    protected function registerTwigLoader(): void
    {
        $this->app->singleton(
            'twig.loader',
            fn(): FilesystemLoader => new FilesystemLoader([])
        );
    }

    /**
     * Register the Twig environment.
     *
     * Uses $c['config']->get() instead of config() to avoid triggering
     * the container's make() while the singleton closure is being built,
     * which would cause an infinite loop.
     *
     * @since 1.0.0
     */
    protected function registerTwigEnvironment(): void
    {
        $this->app->singleton(
            'twig',
            fn($c): Environment => new Environment($c['twig.loader'], [
                'auto_reload' => true,
                'cache'       => $c['path.cache'] . '/Twig',
                'autoescape'  => (bool) $c['config']->get('twig.autoescape', false),
            ])
        );
    }

    /**
     * Register the EngineResolver.
     *
     * @since 1.0.0
     */
    protected function registerEngineResolver(): void
    {
        $this->app->singleton(
            'view.engine.resolver',
            fn(): EngineResolver => $this->createEngineResolver()
        );
    }

    /**
     * Create and configure the EngineResolver.
     *
     * @since 1.0.0
     */
    protected function createEngineResolver(): EngineResolver
    {
        $resolver = new EngineResolver();
        $container = $this->app;

        $resolver->register(
            'twig',
            fn(): TwigEngine => new TwigEngine($container['twig'], $container['view.finder'])
        );

        return $resolver;
    }

    /**
     * Register the ViewFinder and View Factory.
     *
     * @since 1.0.0
     */
    protected function registerViewFactory(): void
    {
        $this->app->singleton(
            'view.finder',
            fn($c): ViewFinder => new ViewFinder($c['files'], [], [])
        );

        $this->app->singleton(
            'view',
            fn($c): Factory => $this->createViewFactory($c)
        );
    }

    /**
     * Create and configure the View Factory.
     *
     * @since 1.0.0
     */
    protected function createViewFactory(mixed $container): Factory
    {
        $factory = new Factory(
            $container['view.engine.resolver'],
            $container['view.finder'],
            new Dispatcher($container)
        );

        $factory->setContainer($container);
        $factory->addExtension('twig', 'twig');
        $factory->share('app', $container);

        return $factory;
    }
}
