<?php

declare(strict_types=1);

namespace Sloth\Layotter;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\ServiceProvider;

/**
 * Service provider for the Layotter component.
 *
 * @since 1.0.0
 * @see ServiceProvider
 */
class LayotterServiceProvider extends ServiceProvider
{
    /**
     * Register the Layotter service provider.
     *
     * @since 1.0.0
     */
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(
            'layotter',
            Layotter::class
        );
    }

    /**
     * Get the required filters for the Layotter service provider.
     *
     * @return array|array[]|callable[]
     * @throws BindingResolutionException
     */
    public function getFilters(): array
    {
        return [
            'layotter/enable_example_element' => ['callback' => '__return_false'],
            'layotter/enable_default_css' => ['callback' => '__return_false'],
            'layotter/enable_element_templates' => ['callback' => '__return_true'],
            'layotter/enable_post_layouts' => ['callback' => '__return_true'],

            'layotter/enabled_post_types' => ['callback' => app('layotter')->enabledPostTypes(...)],
            'layotter/rows/allowed_layouts' => ['callback' => app('layotter')->allowedRowLayouts(...)],
            'layotter/rows/default_layout' => ['callback' => app('layotter')->defaultRowLayout(...)],
            'layotter/columns/classes' => ['callback' => app('layotter')->customColumnClasses(...)],
            'layotter/view/element' => ['callback' => app('layotter')->customElementView(...), 'priority' => 10],
            'layotter/view/column' => ['callback' => app('layotter')->customColumnView(...), 'priority' => 10],
            'layotter/view/row' => ['callback' => app('layotter')->customRowView(...), 'priority' => 10],
            'layotter/view/post' => ['callback' => app('layotter')->customPostView(...), 'priority' => 10],

            'admin_head' => fn() => app('layotter')->renderLayotterStyles(),
        ];
    }
}
