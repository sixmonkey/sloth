<?php

declare(strict_types=1);
namespace Sloth\View\Engines\Twig;

use Illuminate\Contracts\Container\Container;
use Illuminate\View\Factory;
use Sloth\View\Engines\ViewAdapterInterface;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Twig view adapter.
 *
 * Registers the Twig environment in the container and consumes
 * the engine-agnostic helpers, directives and shared variables
 * collected by ViewServiceProvider.
 *
 * Registered internally by ViewServiceProvider — not manually.
 *
 * @since 1.0.0
 */
class TwigAdapter implements ViewAdapterInterface
{
    /**
     * Register Twig container bindings.
     *
     * @since 1.0.0
     *
     * @param Container $app
     */
    public function register(Container $app): void
    {
        // Filesystem loader — paths added by ThemeServiceProvider in boot()
        $app->singleton(
            'twig.loader',
            fn (): FilesystemLoader => new FilesystemLoader([]),
        );

        // Twig environment
        $app->singleton(
            'twig',
            fn ($c): Environment => new Environment($c['twig.loader'], [
                'auto_reload' => $c->isLocal(),
                'cache'       => $c->cachePath('Twig'),
                'autoescape'  => (bool) ($c['config']->get('view.autoescape') ?? $c['config']->get('twig.autoescape', false)),
            ]),
        );
    }

    /**
     * Boot Twig — register helpers, directives, globals and the engine.
     *
     * @since 1.0.0
     *
     * @param Factory   $view
     * @param Container $app
     */
    public function boot(Factory $view, Container $app): void
    {
        /** @var Environment $twig */
        $twig = $app['twig'];

        $twig->addExtension(new DebugExtension());

        if ($app->isLocal()) {
            $twig->enableDebug();
        }

        // _helpers → TwigFilter
        foreach ($view->getShared()['_helpers'] ?? [] as $name => $callable) {
            $twig->addFilter(new TwigFilter($name, $callable));
        }

        // _directives → TwigFunction
        // Also register fn proxy for direct PHP/WordPress function calls
        foreach ($view->getShared()['_directives'] ?? [] as $name => $callable) {
            $twig->addFunction(new TwigFunction($name, $callable));
        }

        // fn global — Twig-only escape hatch for calling any PHP/WP function
        // {{ fn.get_the_title() }}
        $twig->addGlobal('fn', new class {
            public function __call(string $name, array $args): mixed
            {
                return call_user_func_array($name, $args);
            }
        });

        // Sync all registered view paths to the Twig FilesystemLoader
        $app['twig.loader']->setPaths($app['view.finder']->getPaths());

        // Register .twig as a view engine
        $view->addExtension(
            'twig',
            'twig',
            fn (): TwigEngine => new TwigEngine($twig, $app['view.finder']),
        );

        // Sync View::share() to Twig globals.
        // _helpers and _directives are already set by ViewServiceProvider::boot()
        // before adapters are booted, so all shared variables are available here.
        foreach ($view->getShared() as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }
            $twig->addGlobal($key, $value);
        }
    }
}
