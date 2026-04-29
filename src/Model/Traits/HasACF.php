<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Illuminate\Support\Collection;
use Sloth\ACF\AcfProxy;
use Sloth\Model\Casts\AcfBase;

/**
 * Provides Advanced Custom Fields (ACF) integration for models.
 *
 * This trait enables automatic ACF field retrieval for models by:
 * - Caching ACF fields per post/object
 * - Automatically casting ACF fields to the correct types
 * - Providing a proxy object for accessing ACF fields
 *
 * Models using this trait must implement getAcfKey().
 *
 * @since 1.0.0
 * @see \Sloth\ACF\AcfProxy
 * @see \Sloth\Model\Casts\AcfBase
 * @see https://www.advancedcustomfields.com/ ACF Plugin
 */
trait HasACF
{
    /**
     * Cache of ACF fields per object key.
     *
     * @var array<string, Collection>
     */
    public static array $acfFieldCache = [];

    /**
     * Boot the HasACF trait.
     *
     * Registers a 'retrieved' event that automatically:
     * - Fetches ACF fields for the model
     * - Merges ACF field casts into the model
     *
     * @since 1.0.0
     */
    public static function bootHasACF(): void
    {
        static::retrieved(function (self $model) {
            $fields = $model->getFields($model);
            $acf_fields = $fields->keys();
            $native_fields = collect($model->getAttributes())->keys();

            $acf_casts = $acf_fields->diff($native_fields)->mapWithKeys(function ($item) {
                return [$item => AcfBase::class];
            });

            $model->mergeCasts($acf_casts->toArray());
        });
    }

    /**
     * Get ACF fields for a model, with caching.
     *
     * @param mixed $model The model instance
     * @return Collection<string, mixed> Collection of ACF fields
     * @since 1.0.0
     */
    private function getFields(mixed $model): Collection
    {
        $key = $model->getAcfKey();
        if (!isset(static::$acfFieldCache[$key])) {
            static::$acfFieldCache[$key] = collect(get_fields($key) ?? []);
        }
        return static::$acfFieldCache[$key];
    }

    /**
     * Get the ACF field group key for this model.
     *
     * @return string|null The ACF field group key (post ID, user ID, etc.)
     */
    abstract public function getAcfKey(): ?string;

    /**
     * Get an ACF proxy for accessing fields.
     *
     * @return AcfProxy Proxy object for ACF field access
     * @since 1.0.0
     */
    public function getAcfAttribute(): AcfProxy
    {
        return new AcfProxy($this->getFields($this));
    }
}
