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
            'layotter/enable_example_element' => '__return_false',
            'layotter/enable_default_css' => '__return_false',
            'layotter/enable_element_templates' => '__return_true',
            'layotter/enable_post_layouts' => '__return_true',

            'layotter/enabled_post_types' => fn(...$args) => app('layotter')->enabledPostTypes(...$args),
            'layotter/rows/allowed_layouts' => fn(...$args) => app('layotter')->allowedRowLayouts(...$args),
            'layotter/rows/default_layout' => fn(...$args) => app('layotter')->defaultRowLayout(...$args),
            'layotter/columns/classes' => fn(...$args) => app('layotter')->customColumnClasses(...$args),
            'layotter/view/element' => [
                'callback' => fn(...$args) => app('layotter')->customElementView(...$args),
                'priority' => 10
            ],
            'layotter/view/column' => [
                'callback' => fn(...$args) => app('layotter')->customColumnView(...$args),
                'priority' => 10
            ],
            'layotter/view/row' => [
                'callback' => fn(...$args) => app('layotter')->customRowView(...$args),
                'priority' => 10
            ],
            'layotter/view/post' => [
                'callback' => fn(...$args) => app('layotter')->customPostView(...$args),
                'priority' => 10
            ],

            'admin_head' => fn() => app('layotter')->renderLayotterStyles(),
        ];
    }
}
