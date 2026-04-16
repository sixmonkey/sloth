<?php

namespace Sloth\Context;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Context component.
 *
 * Handles template context building for Twig templates, including
 * WordPress site data, post/taxonomy/author context, and Sloth-specific
 * variables like current layout.
 *
 * @since 1.0.0
 * @see \Sloth\Context\Context
 * @see \Sloth\Plugin\Plugin
 */
class ContextServiceProvider extends ServiceProvider
{
    /**
     * Register the Context singleton.
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        $this->app->singleton('context', function () {
            return new Context();
        });
    }
}
