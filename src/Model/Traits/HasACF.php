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
 */
trait HasACF
{
    /**
     * Boot the trait.
     */
    public static function bootHasACF(): void
    {
        if (!Configure::check('sloth.acf.process') || !Configure::read('sloth.acf.process')) {
            return;
        }

        static::retrieved(function ($model) {
            $key = $model->getAcfKey();
            $fieldObjects = get_field_objects($key) ?: [];

            $native_fields = collect($model->getAttributes())->keys();
            $acf_casts = collect($fieldObjects)
                ->filter(fn($field) => !in_array($field['name'], $native_fields->toArray()))
                ->mapWithKeys(function ($field) {
                    return match ($field['type']) {
                        'image' => [$field['name'] => ACFImage::class],
                        'date_picker', 'date_time_picker', 'time_picker' => [$field['name'] => ACFDate::class],
                        default => [$field['name'] => ACF::class]
                    };
                });

            $model->mergeCasts($acf_casts->toArray());
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
}
