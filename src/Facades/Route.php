<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Override;
use Sloth\Routing\Router;

/**
 * Route Facade.
 *
 * @method static \Sloth\Routing\Route get(string $path, callable|array $callback)
 * @method static \Sloth\Routing\Route post(string $path, callable|array $callback)
 * @method static \Sloth\Routing\Route put(string $path, callable|array $callback)
 * @method static \Sloth\Routing\Route delete(string $path, callable|array $callback)
 * @method static array|null match(string $path, string $method)
 * @method static string url(string $name, array $params = [])
 *
 * @mixin Router
 *
 * @see Router
 * @since 1.0.0
 */
class Route extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'router';
    }
}
