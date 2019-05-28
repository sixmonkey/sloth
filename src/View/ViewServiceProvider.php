<?php

namespace Sloth\View;

use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Sloth\Core\ServiceProvider;
use Sloth\View\Engines\TwigEngine;
use Sloth\View\Extensions\SlothTwigExtension;
use Illuminate\Events\Dispatcher;
use Sloth\Configure\Configure;

class ViewServiceProvider extends ServiceProvider {
    public function register() {
        $this->registerTwigEnvironment();
        $this->registerEngineResolver();
        $this->registerViewFactory();
    }

    /**
     * Register the EngineResolver instance to the application.
     */
    protected function registerEngineResolver() {
        $serviceProvider = $this;

        $this->app->singleton( 'view.engine.resolver',
            function () use ( $serviceProvider ) {
                $resolver = new EngineResolver();

                // Register the engines.
                foreach ( [ 'twig' ] as $engine ) {
                    $serviceProvider->{'register' . ucfirst( $engine ) . 'Engine'}( $engine, $resolver );
                }

                return $resolver;
            } );
    }

    /**
     * Register the Twig engine to the EngineResolver.
     *
     * @param string         $engine
     * @param EngineResolver $resolver
     */
    protected function registerTwigEngine( $engine, EngineResolver $resolver ) {
        $container = $this->app;

        $resolver->register( $engine,
            function () use ( $container ) {

                // Set the loader main namespace (paths).

                return new TwigEngine( $container['twig'], $container['view.finder'] );
            } );
    }

    /**
     * Register Twig environment and its loader.
     */
    protected function registerTwigEnvironment() {
        $container = $this->app;

        // Twig Filesystem loader.
        $container->singleton( 'twig.loader',
            function () {
                return new \Twig_Loader_Filesystem( DIR_ROOT );
            } );

        // Twig
        $container->singleton( 'twig',
            function ( $container ) {
                return new \Twig_Environment( $container['twig.loader'], [
                    'auto_reload' => true,
                    'cache'       => $container['path.cache'] . 'Twig',
                    'autoescape'  => (bool) Configure::read( 'twig.autoescape' ),
                ] );
            } );

        // Add the dump Twig extension.
        $container['twig']->addExtension( new \Twig_Extension_Debug() );

        // Check if debug constant exists and set to true.
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $container['twig']->enableDebug();
        }

        // Provides WordPress functions and more to Twig templates.
        $container['twig']->addExtension( new SlothTwigExtension( $container ) );
    }

    /**
     * Register the view factory. The factory is
     * available in all views.
     */
    protected function registerViewFactory() {
        // Register the View Finder first.
        $this->app->singleton( 'view.finder',
            function ( $container ) {
                return new ViewFinder( $container['filesystem'], [], [] );
            } );

        $this->app->singleton( 'view',
            function ( $container ) {
                $factory = new Factory( $container['view.engine.resolver'],
                    $container['view.finder'],
                    new Dispatcher( $container ) );
                // Set the container.
                $factory->setContainer( $container );
                // Tell the factory to handle twig extension files and assign them to the twig engine.
                $factory->addExtension( 'twig', 'twig' );

                // We will also set the container instance on this view environment since the
                // view composers may be classes registered in the container, which allows
                // for great testable, flexible composers for the application developer.
                $factory->setContainer( $container );

                $factory->share( 'app', $container );

                return $factory;
            } );
    }
}
