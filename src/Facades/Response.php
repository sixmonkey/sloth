<?php

declare(strict_types=1);
namespace Sloth\Facades;

use Override;
use Sloth\Http\Response as SlothResponse;

/**
 * Response Facade.
 *
 * @method static \Sloth\Http\Response make(mixed $content = '', int $status = 200, array $headers = [])
 *
 * @mixin SlothResponse
 *
 * @see SlothResponse
 * @since 1.0.0
 */
class Response extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return 'response';
    }
}
