<?php

declare(strict_types=1);

namespace Sloth\Admin;

use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Admin Customizer component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class CustomizerServiceProvider extends ServiceProvider
{
    /**
     * Register the Customizer service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'customizer',
            fn($container): \Sloth\Admin\Customizer => Customizer::getInstance()
        );
    }
}
