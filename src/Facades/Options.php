<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Override;
use Sloth\Options\Options as SlothOptions;

/**
 * Options Facade.
 *
 * @method static mixed get(string $key, mixed $default = null)
 * @method static bool  set(string $key, mixed $value)
 * @method static bool  has(string $key)
 * @method static bool  delete(string $key)
 *
 * @mixin SlothOptions
 *
 * @see SlothOptions
 * @since 1.0.0
 */
class Options extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'options';
    }
}
