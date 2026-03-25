<?php

namespace Sloth\Model\Casts;

use Corcel\Model\Attachment;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Sloth\Field\Image;

/**
 * ACF image field cast.
 *
 * Converts ACF image field values to Sloth\Field\Image objects.
 * Handles both array format (from return format setting) and integer IDs.
 * Fetches attachment URL from Corcel if available, otherwise returns Image with ID.
 */
class ACFImage implements CastsAttributes
{
    /**
     * Get the ACF image field as an Image object.
     *
     * Handles various ACF image return formats:
     * - Array with 'ID' key: extracts the ID
     * - Integer ID: uses directly
     * - Empty/null: returns null
     *
     * When an attachment exists in the database, uses its URL for the Image object.
     * Otherwise, creates Image with the ID for lazy loading.
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return mixed The Image object, or null if empty/invalid
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $acfKey = $model->getAcfKey();

        if (property_exists($model, 'acfFieldCache') && isset($model::$acfFieldCache[$acfKey]['values'])) {
            $value = $model::$acfFieldCache[$acfKey]['values'][$key] ?? null;
        }

        if (empty($value)) {
            return null;
        }

        $id = is_array($value) ? (int) ($value['ID'] ?? 0) : (int) $value;

        if ($id === 0) {
            return null;
        }

        $attachment = Attachment::find($id);
        if (is_object($attachment)) {
            return new Image($attachment->url);
        }

        return new Image($id);
    }

    /**
     * Set the ACF image field value.
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
