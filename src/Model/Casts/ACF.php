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
class ACF implements CastsAttributes
{
    /**
     * Get the ACF field value.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return mixed The field value
     */
    public function get($model, $key, $value, $attributes)
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
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The value to set
     * @param array $attributes All model attributes
     * @return mixed The value to store
     */
    public function set($model, $key, $value, $attributes)
    {
        update_field($key, $value, $model->getAcfKey());

        return $value;
    }
}
