<?php

namespace Sloth\Model\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic ACF field cast.
 *
 * Retrieves ACF field values, using the HasACF trait's cache when available
 * to avoid redundant get_field() calls. Falls back to get_field() if no cache exists.
 */
class ACF implements CastsAttributes
{
    /**
     * Get the ACF field value.
     *
     * First checks for cached values from HasACF trait, then falls back to get_field().
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return mixed The field value
     */
    public function get(Model $model, string $key, mixed $value, array $attributes)
    {
        $acfKey = $model->getAcfKey();

        if (property_exists($model, 'acfFieldCache') && isset($model::$acfFieldCache[$acfKey]['values'])) {
            return $model::$acfFieldCache[$acfKey]['values'][$key] ?? null;
        }

        return get_field($key, $acfKey);
    }

    /**
     * Set the ACF field value.
     *
     * Uses update_field() to properly save the value to ACF.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The value to set
     * @param array $attributes All model attributes
     * @return mixed The value to store (can be transformed)
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        update_field($key, $value, $model->getAcfKey());

        return $value;
    }
}
