<?php

declare(strict_types=1);
namespace Sloth\Context;

use Override;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Context component.
 *
 * Handles template context building for Twig templates, including
 * WordPress site data, post/taxonomy/author context, and Sloth-specific
 * variables like current layout.
 *
 * @since 1.0.0
 * @see Context
 * @see \Sloth\Plugin\Plugin
 */
class ContextServiceProvider extends ServiceProvider
{
    /**
     * Register the Context singleton.
     *
     * @since 1.0.0
     */
    #[Override]
    public function register(): void
    {
        $this->app->singleton('context', fn (): Context => new Context($this->app));
    }
}
