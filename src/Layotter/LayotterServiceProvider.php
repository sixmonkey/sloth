<?php

declare(strict_types=1);

namespace Sloth\Layotter;

use Illuminate\Contracts\Container\BindingResolutionException;
use Sloth\Core\ServiceProvider;
use Sloth\Model\Model;

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
     * Configure Layotter page builder integration for a model.
     *
     * Reads $modelClass::$layotter — falls back to false if not declared.
     *
     * - false → Layotter disabled for this post type
     * - true  → Layotter enabled with default settings
     * - array → Layotter enabled with custom settings (e.g. allowed_row_layouts)
     *
     * Skips silently if Layotter is not bound in the container.
     *
     * @param class-string<Model> $modelClass The model class name.
     * @param string $postType The post type slug.
     * @since 1.0.0
     *
     */
    protected function configurePostTypes(): void
    {
        collect($this->app->get('sloth.models'))->each(function (string $modelClass, string $postType) {
            try {
                $layotter = $modelClass::$layotter;
                $layotterService = $this->app['layotter'];
            } catch (\Throwable) {
                return;
            }

            if ($layotter === false) {
                $layotterService->disable_for_post_type($postType);
                return;
            }

            $layotterService->enable_for_post_type($postType);

            if (is_array($layotter) && isset($layotter['allowed_row_layouts'])) {
                $layotterService->set_layouts_for_post_type(
                    $postType,
                    $layotter['allowed_row_layouts']
                );
            }
        });
    }

    /**
     * @return array[]
     */
    public function getHooks(): array
    {
        return [
            'init' => ['callback' => fn() => $this->configurePostTypes(), 'priority' => 20],
        ];
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
                'priority' => 10,
            ],
            'layotter/view/column' => [
                'callback' => fn(...$args) => app('layotter')->customColumnView(...$args),
                'priority' => 10,
            ],
            'layotter/view/row' => [
                'callback' => fn(...$args) => app('layotter')->customRowView(...$args),
                'priority' => 10,
            ],
            'layotter/view/post' => [
                'callback' => fn(...$args) => app('layotter')->customPostView(...$args),
                'priority' => 10,
            ],

            'admin_head' => fn() => app('layotter')->renderLayotterStyles(),
        ];
    }
}
