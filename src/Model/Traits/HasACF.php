<?php

namespace Sloth\Model\Traits;

use App\Model\User;
use Sloth\Facades\Configure;
use Sloth\Model\Casts\ACF;
use Sloth\Model\Casts\ACFDate;
use Sloth\Model\Casts\ACFImage;
use Sloth\Model\Taxonomy;

/**
 * Trait for automatic ACF field casting.
 *
 * When sloth.acf.process is enabled, this trait dynamically adds cast definitions
 * for ACF fields based on their type:
 * - image fields → ACFImage cast (returns Sloth\Field\Image object)
 * - date fields → ACFDate cast (returns Carbon or CarbonFaker for empty values)
 * - other fields → ACF cast (returns raw value from get_field)
 *
 * Uses a static cache to avoid calling get_fields() multiple times per model instance.
 */
trait HasACF
{
    /**
     * Static cache for ACF field data, keyed by ACF identifier (post ID, term_XX, user_XX).
     * Stores both field objects (definitions) and values for efficient cast access.
     */
    protected static array $acfFieldCache = [];

    /**
     * Initialize ACF casts on model retrieval.
     *
     * Only runs when sloth.acf.process is enabled. Fetches all ACF field definitions
     * and values once, then dynamically adds cast mappings for non-native fields.
     */
    public function initializeHasACF(): void
    {
        if (!Configure::check('sloth.acf.process') || !Configure::read('sloth.acf.process')) {
            return;
        }

        static::retrieved(function () {
            $key = $this->getAcfKey();

            if (!isset(self::$acfFieldCache[$key])) {
                $fieldObjects = get_field_objects($key) ?: [];
                $fieldValues = get_fields($key) ?: [];

                self::$acfFieldCache[$key] = [
                    'objects' => $fieldObjects,
                    'values' => $fieldValues,
                ];
            }

            $native_fields = collect($this->getAttributes())->keys();
            $acf_casts = collect(self::$acfFieldCache[$key]['objects'])
                ->filter(fn($field) => !in_array($field['name'], $native_fields->toArray()))
                ->mapWithKeys(function ($field) {
                    return match ($field['type']) {
                        'image' => [$field['name'] => ACFImage::class],
                        'date_picker', 'date_time_picker', 'time_picker' => [$field['name'] => ACFDate::class],
                        default => [$field['name'] => ACF::class]
                    };
                });

            $this->mergeCasts($acf_casts->toArray());
        });
    }

    /**
     * Get the ACF identifier for this model instance.
     *
     * @return string|null Post ID, term_XX for taxonomies, or user_XX for users
     */
    public function getAcfKey(): ?string
    {
        return match (true) {
            is_a($this, Taxonomy::class) => 'term_' . $this->getAttribute('term_id'),
            is_a($this, User::class) => 'user_' . $this->getAttribute('ID'),
            default => $this->getAttribute('ID')
        };
    }

    /**
     * Get all ACF field values for this model.
     *
     * Uses cached values if available, otherwise fetches from ACF.
     *
     * @return false|array All field values, or false if no fields exist
     */
    public function getAcfAttribute(): false|array
    {
        $key = $this->getAcfKey();
        if (!isset(self::$acfFieldCache[$key])) {
            self::$acfFieldCache[$key] = [
                'objects' => get_field_objects($key) ?: [],
                'values' => get_fields($key) ?: [],
            ];
        }

        return self::$acfFieldCache[$key]['values'];
    }
}
