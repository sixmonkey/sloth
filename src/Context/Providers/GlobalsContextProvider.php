<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides global URL variables to the Twig context.
 *
 * Available in templates as {{ globals.home_url }}, {{ globals.theme_url }}, etc.
 *
 * @since 1.0.0
 */
class GlobalsContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'globals';
    }

    public function resolve(): array
    {
        return [
            'home_url'   => home_url('/'),
            'theme_url'  => get_template_directory_uri(),
            'images_url' => get_template_directory_uri() . '/assets/img',
        ];
    }
}
