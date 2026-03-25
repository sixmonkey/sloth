<?php

namespace Sloth\Model\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Sloth\Field\CarbonFaker;

/**
 * ACF date/time field cast.
 *
 * Converts ACF date field values to Carbon instances.
 */
class ACFDate implements CastsAttributes
{
    /**
     * Get the ACF date field as a Carbon instance.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return Carbon|CarbonFaker The parsed date, or CarbonFaker for empty values
     */
    public function get($model, $key, $value, $attributes)
    {
        $acfKey = $model->getAcfKey();
        $value = get_field($key, $acfKey);

        if (empty($value)) {
            return new CarbonFaker();
        }

        $fieldObject = get_field_object($key, $acfKey);
        $type = is_array($fieldObject) ? ($fieldObject['type'] ?? 'date_picker') : 'date_picker';

        return match ($type) {
            'date_time_picker' => Carbon::parse($value),
            'time_picker' => Carbon::parse($value),
            default => Carbon::parse($value)->startOfDay()
        };
    }

    /**
     * Set the ACF date field value.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The value to set
     * @param array $attributes All model attributes
     * @return mixed The value to store
     */
    public function set($model, $key, $value, $attributes)
    {
        return $value;
    }
}
