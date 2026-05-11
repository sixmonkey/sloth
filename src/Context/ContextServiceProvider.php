<?php

declare(strict_types=1);
namespace Sloth\Context;

use Override;
use Sloth\Context\Providers\AuthorContextProvider;
use Sloth\Context\Providers\GlobalsContextProvider;
use Sloth\Context\Providers\OptionsContextProvider;
use Sloth\Context\Providers\PostContextProvider;
use Sloth\Context\Providers\SiteContextProvider;
use Sloth\Context\Providers\SlothContextProvider;
use Sloth\Context\Providers\TaxonomyContextProvider;
use Sloth\Context\Providers\WpTitleContextProvider;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Context component.
 *
 * Registers the Context singleton and all built-in context providers.
 * The Context is lazy — providers are only resolved when their key
 * is accessed in a Twig template.
 *
 * ## Adding custom providers
 *
 * Drop a ContextProvider subclass in app/Context/ or theme/Context/
 * for auto-discovery, or register manually in any service provider:
 *
 * ```php
 * public function boot(): void
 * {
 *     app('context')->register(new MyContextProvider());
 * }
 * ```
 *
 * @since 1.0.0
 * @see Context
 * @see ContextProvider
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
        $this->app->singleton(
            'context',
            fn (): Context => new Context($this->app),
        );
    }

    /**
     * Register all built-in context providers.
     *
     * Framework providers are registered here. Theme providers are
     * auto-discovered from app/Context/ and theme/Context/ by the
     * ContextManifestBuilder.
     *
     * @since 1.0.0
     */
    #[Override]
    public function boot(): void
    {
        $context = $this->app['context'];

        $context
            ->register(new WpTitleContextProvider())
            ->register(new SiteContextProvider())
            ->register(new GlobalsContextProvider())
            ->register(new SlothContextProvider())
            ->register(new PostContextProvider())
            ->register(new TaxonomyContextProvider())
            ->register(new AuthorContextProvider())
            ->register(new OptionsContextProvider());
    }
}
