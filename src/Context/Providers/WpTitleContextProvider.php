<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides the WordPress page title to the Twig context.
 *
 * Available in templates as {{ wp_title }}.
 *
 * @since 1.0.0
 */
class WpTitleContextProvider extends ContextProvider
{
    public function key(): string
    {
        return 'wp_title';
    }

    public function resolve(): string
    {
        return trim((string) wp_title('', false));
    }
}
