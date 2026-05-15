<?php

declare(strict_types=1);
namespace Sloth\Core\Traits;

use Deprecated;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Sloth\Model\Model;
use Sloth\Model\Taxonomy;

/**
 * Convenience helpers used in theme code and templates.
 *
 * Most methods are thin delegates to container bindings. Kept in a
 * dedicated trait so the Application class stays focused on lifecycle.
 *
 * @since 2.0.0
 */
trait ApplicationConvenienceTrait
{
    // -------------------------------------------------------------------------
    // Backwards compatibility
    // -------------------------------------------------------------------------

    /**
     * Get the template context.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    #[Deprecated(message: "use app('context')->getContext() instead")]
    public function getContext(): array
    {
        return $this->bound('context') ? $this['context']->getContext() : [];
    }

    /**
     * Check if running in a development environment.
     *
     * @since 1.0.0
     */
    #[Deprecated(message: 'use app()->isLocal() instead')]
    public function isDevEnv(): bool
    {
        return $this->isLocal();
    }

    /**
     * Get the class name for a model by its post_type.
     *
     * @param string $key post type slug
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.models')[$key] directly.
     *
     * @since 1.0.0
     */
    public function getModelClass(string $key = ''): string
    {
        return app('sloth.models')[$key] ?? Model::class;
    }

    /**
     * Get all registered models.
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.models') directly.
     *
     * @since 1.0.0
     */
    public function getAllModels(): Collection
    {
        return collect(app('sloth.models'));
    }

    /**
     * Get the class name for a taxonomy by its slug.
     *
     * @param string $key taxonomy slug
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.taxonomies')[$key] directly.
     *
     * @since 1.0.0
     */
    public function getTaxonomyClass(string $key = ''): string
    {
        return app('sloth.taxonomies')[$key] ?? Taxonomy::class;
    }

    /**
     * Get all registered taxonomies.
     *
     * @throws BindingResolutionException
     *
     * @todo Deprecate — use app('sloth.taxonomies') directly.
     *
     * @since 1.0.0
     */
    public function getAllTaxonomies(): Collection
    {
        return collect(app('sloth.taxonomies'));
    }

    /**
     * Get the application version.
     *
     * @since 1.0.0
     */
    public function version(): string
    {
        return self::version;
    }
}
