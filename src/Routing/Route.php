<?php

declare(strict_types=1);
namespace Sloth\Routing;

use Symfony\Component\Routing\Route as SymfonyRoute;

/**
 * Thin wrapper around Symfony's Route.
 *
 * Stores the callback as _controller default and provides
 * fluent name() chaining back through the Router.
 *
 * @since 1.0.0
 */
class Route extends SymfonyRoute
{
    /**
     * @param string         $path     Route path (e.g. /posts/{slug})
     * @param array|callable $callback Route handler stored as _controller
     * @param list<string>   $methods  HTTP methods
     * @param Router         $router   Parent router for name registration
     *
     * @since 1.0.0
     */
    public function __construct(
        string $path,
        mixed $callback,
        array $methods,
        private readonly Router $router,
    ) {
        parent::__construct(
            path: $path,
            defaults: ['_controller' => $callback],
            methods: $methods,
        );
    }

    /**
     * Assign a name to this route.
     *
     * @since 1.0.0
     *
     * @param string $name
     */
    public function name(string $name): static
    {
        $this->router->registerName($name, $this);

        return $this;
    }
}
