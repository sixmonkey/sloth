<?php

declare(strict_types=1);

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
 */
class ACFImage implements CastsAttributes
{
    /**
     * Get the ACF image field as an Image object.
     *
     * @since 1.0.0
     *
     * @param Model $model The model instance
     * @param string $key The field name
     * @param mixed $value The raw value from the database
     * @param array $attributes All model attributes
     * @return Image|null The Image object, or null if empty/invalid
     */
    public function get($model, $key, $value, $attributes): ?Image
    {
        $acfKey = $model->getAcfKey();
        $value = get_field($key, $acfKey);

        if (empty($value)) {
            return null;
        }

        if (is_array($value)) {
            if (isset($value['ID']) && (int) $value['ID'] > 0) {
                $attachment = Attachment::find((int) $value['ID']);
                if (is_object($attachment)) {
                    return new Image($attachment->url);
                }

                return new Image((int) $value['ID']);
            }

            if (isset($value['url'])) {
                return new Image($value);
            }

            return null;
        }

        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return new Image($value);
        }

        $id = (int) $value;
        if ($id > 0) {
            $attachment = Attachment::find($id);
            if (is_object($attachment)) {
                return new Image($attachment->url);
            }

            return new Image($id);
        }

        return null;
    }

    /**
     * Set the ACF image field value.
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
