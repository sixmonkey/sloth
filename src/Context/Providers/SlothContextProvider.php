<?php

declare(strict_types=1);
namespace Sloth\Context\Providers;

use Sloth\Context\ContextProvider;

/**
 * Provides Sloth-specific variables to the Twig context.
 *
 * Available in templates as {{ sloth.current_layout }}, etc.
 *
 * @since 1.0.0
 */
class SlothContextProvider extends ContextProvider
{
    public function __construct(private readonly ?string $currentLayout = null)
    {
    }

    public function key(): string
    {
        return 'sloth';
    }

    public function resolve(): array
    {
        return [
            'current_layout' => basename($this->currentLayout ?? '', '.twig'),
        ];
    }
}
