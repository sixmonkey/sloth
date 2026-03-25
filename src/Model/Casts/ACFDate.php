<?php

namespace Sloth\Model\Casts;

use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Sloth\Field\CarbonFaker;

/**
 * ACF date/time field cast.
 *
 * Converts ACF date field values to Carbon instances:
 * - date_picker: Returns Carbon at start of day
 * - date_time_picker: Returns Carbon with full datetime
 * - time_picker: Returns Carbon with time only
 * - Empty values: Returns CarbonFaker instance
 *
 * Uses the field type from ACF field objects to determine parsing behavior.
 */
class ACFDate implements CastsAttributes
{
    /**
     * Get the ACF date field as a Carbon instance.
     *
     * Returns CarbonFaker for empty values to allow consistent handling
     * when dates haven't been set yet. The field type determines whether
     * to preserve time information.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return mixed The parsed date (Carbon/CarbonFaker), or null for empty values
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $acfKey = $model->getAcfKey();

        if (property_exists($model, 'acfFieldCache') && isset($model::$acfFieldCache[$acfKey]['values'][$key])) {
            $value = $model::$acfFieldCache[$acfKey]['values'][$key];
        }

        if (empty($value)) {
            return new CarbonFaker();
        }

        $fieldObjects = [];
        if (property_exists($model, 'acfFieldCache') && isset($model::$acfFieldCache[$acfKey]['objects'][$key])) {
            $fieldObjects = $model::$acfFieldCache[$acfKey]['objects'][$key];
        }

        $type = $fieldObjects['type'] ?? 'date_picker';

        return match ($type) {
            'date_time_picker' => Carbon::parse($value),
            'time_picker' => Carbon::parse($value),
            default => Carbon::parse($value)->startOfDay()
        };
    }

    /**
     * Set the ACF date field value.
     *
     * Passes through the value unchanged - ACF handles the actual saving.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The value to set
     * @param array $attributes All model attributes
     * @return mixed The value to store
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value;
    }
}
