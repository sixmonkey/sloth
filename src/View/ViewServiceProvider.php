<?php

declare(strict_types=1);

namespace Sloth\View;

use Illuminate\Events\Dispatcher;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Engines\PhpEngine;
use Illuminate\View\Factory;
use Sloth\Core\ServiceProvider;
use Sloth\View\Engines\TwigEngine;
use Sloth\View\Extensions\SlothTwigExtension;
use Sloth\Configure\Configure;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;

/**
 * Service provider for the View rendering component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class ViewServiceProvider extends ServiceProvider {
	/**
	 * Register the View service provider.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register(): void {
		$this->registerTwigEnvironment();
		$this->registerEngineResolver();
		$this->registerViewFactory();
	}

	/**
	 * Register the EngineResolver instance to the application.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerEngineResolver(): void {
		$this->app->singleton(
			'view.engine.resolver',
			fn() => $this->createEngineResolver()
		);
	}

	/**
	 * Create and configure the EngineResolver.
	 *
	 * @since 1.0.0
	 *
	 * @return EngineResolver
	 */
	protected function createEngineResolver(): EngineResolver {
		$resolver = new EngineResolver();
		$this->registerTwigEngine('twig', $resolver);

		return $resolver;
	}

	/**
	 * Register the Twig engine to the EngineResolver.
	 *
	 * @since 1.0.0
	 *
	 * @param string         $engine   The engine name
	 * @param EngineResolver $resolver The engine resolver
	 *
	 * @return void
	 */
	protected function registerTwigEngine(string $engine, EngineResolver $resolver): void {
		$container = $this->app;

		$resolver->register(
			$engine,
			fn() => new TwigEngine($container['twig'], $container['view.finder'])
		);
	}

	/**
	 * Register Twig environment and its loader.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerTwigEnvironment(): void {
		$container = $this->app;

		$container->singleton(
			'twig.loader',
			fn() => new FilesystemLoader(DIR_ROOT)
		);

		$container->singleton(
			'twig',
			fn($c) => new Environment($c['twig.loader'], [
				'auto_reload' => true,
				'cache'       => $c['path.cache'] . 'Twig',
				'autoescape'  => (bool) Configure::read('twig.autoescape'),
			])
		);

		$container['twig']->addExtension(new DebugExtension());

		if (defined('WP_DEBUG') && WP_DEBUG) {
			$container['twig']->enableDebug();
		}

		$container['twig']->addExtension(new SlothTwigExtension($container));
	}

	/**
	 * Register the view factory.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function registerViewFactory(): void {
		$this->app->singleton(
			'view.finder',
			fn($container) => new ViewFinder($container['filesystem'], [], [])
		);

		$this->app->singleton(
			'view',
			fn($container) => $this->createViewFactory($container)
		);
	}

	/**
	 * Create and configure the view factory.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $container The container instance
	 *
	 * @return Factory
	 */
	protected function createViewFactory($container): Factory {
		$factory = new Factory(
			$container['view.engine.resolver'],
			$container['view.finder'],
			new Dispatcher($container)
		);

		$factory->setContainer($container);
		$factory->addExtension('twig', 'twig');
		$factory->setContainer($container);
		$factory->share('app', $container);

		return $factory;
	}
}
