<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides global URL variables to the Twig context.
 *
 * Available in templates as {{ globals.home_url }}, {{ globals.theme_url }}, etc.
 * Uses app()->uri() which is resolved during bootstrap — no direct WP calls needed.
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
            'home_url'   => app()->uri(),
            'theme_url'  => app()->uri('', 'theme'),
            'images_url' => app()->uri('', 'theme') . '/assets/img',
        ];
    }
}
