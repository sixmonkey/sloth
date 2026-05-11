<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides the Options accessor to the Twig context.
 *
 * Available in templates as {{ options.blogname }}, {{ options.my_acf_field }}, etc.
 *
 * @since 1.0.0
 */
class OptionsContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'options';
    }

    public function resolve(): mixed
    {
        return app('options');
    }
}
