<?php

declare(strict_types=1);

namespace Sloth\Model\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic ACF field cast.
 *
 * Retrieves ACF field values using get_field().
 */
class AcfBase implements CastsAttributes
{
    /**
     * Get the ACF field value.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return mixed The field value
     * @since 1.0.0
     *
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $acfKey = $model->getAcfKey();

        // Check the cache first
        #if (property_exists($model, 'acfFieldCache') && isset($model::$acfFieldCache[$acfKey][$key])) {
        #    return $model::$acfFieldCache[$acfKey][$key];
        #}

        // Fallback to get_field() if the field doesn't exist in the cache
        return get_field($key, $model->getAcfKey());
    }

    /**
     * Set the ACF field value.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The value to set
     * @param array $attributes All model attributes
     * @return mixed The value to store
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        update_field($key, $value, $model->getAcfKey());

        return $value;
    }
}
