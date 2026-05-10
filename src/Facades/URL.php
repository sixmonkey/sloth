<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Override;
use Sloth\Routing\UrlGenerator;

/**
 * URL Facade.
 *
 * @method static string home(string $path = '')
 * @method static string to(string $path)
 * @method static string theme(string $path = '')
 * @method static string asset(string $path)
 * @method static string content(string $path = '')
 * @method static string uploads(string $path = '')
 * @method static string route(string $name, array $params = [])
 * @method static string current()
 * @method static string full()
 *
 * @mixin UrlGenerator
 *
 * @see UrlGenerator
 * @since 1.0.0
 */
class URL extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'url';
    }
}
