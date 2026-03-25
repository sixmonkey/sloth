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
 * When sloth.acf.process is enabled, this trait overrides getAttribute to apply
 * ACF casts based on field type:
 * - image fields → ACFImage cast (returns Sloth\Field\Image object)
 * - date fields → ACFDate cast (returns Carbon or CarbonFaker for empty values)
 * - other fields → ACF cast (returns raw value from get_field)
 */
trait HasACF
{
    /**
     * Cached ACF field definitions.
     */
    protected static array $acfFieldCache = [];

    /**
     * Get an attribute.
     *
     * @param string $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!function_exists('get_field_objects')) {
            return parent::getAttribute($key);
        }

        if (!Configure::check('sloth.acf.process') || !Configure::read('sloth.acf.process')) {
            return parent::getAttribute($key);
        }

        $acfKey = $this->getAcfKeyValue();
        if ($acfKey === null) {
            return parent::getAttribute($key);
        }

        $castType = $this->getAcfCastType($acfKey, $key);

        if ($castType === 'image') {
            $cast = new ACFImage();
            return $cast->get($this, $key, parent::getAttribute($key), $this->getAttributes());
        }

        if ($castType === 'date') {
            $cast = new ACFDate();
            return $cast->get($this, $key, parent::getAttribute($key), $this->getAttributes());
        }

        if ($castType === 'generic') {
            $cast = new ACF();
            return $cast->get($this, $key, parent::getAttribute($key), $this->getAttributes());
        }

        return parent::getAttribute($key);
    }

    /**
     * Get the ACF cast type for a field.
     *
     * @param string|null $acfKey
     * @param string $fieldKey
     * @return string|null
     */
    protected function getAcfCastType(?string $acfKey, string $fieldKey): ?string
    {
        if ($acfKey === null) {
            return null;
        }

        $cacheKey = $acfKey . '_types';
        if (!isset(self::$acfFieldCache[$cacheKey])) {
            $fieldObjects = get_field_objects($acfKey) ?: [];
            self::$acfFieldCache[$cacheKey] = [];
            foreach ($fieldObjects as $field) {
                self::$acfFieldCache[$cacheKey][$field['name']] = $field['type'];
            }
        }

        if (!isset(self::$acfFieldCache[$cacheKey][$fieldKey])) {
            return null;
        }

        $type = self::$acfFieldCache[$cacheKey][$fieldKey];

        return match ($type) {
            'image' => 'image',
            'date_picker', 'date_time_picker', 'time_picker' => 'date',
            default => 'generic'
        };
    }

    /**
     * Get the ACF identifier for this model instance.
     *
     * @return string|null Post ID, term_XX for taxonomies, or user_XX for users
     */
    public function getAcfKey(): ?string
    {
        return $this->getAcfKeyValue();
    }

    /**
     * Get the raw ACF key value.
     *
     * @return string|null
     */
    protected function getAcfKeyValue(): ?string
    {
        if (is_a($this, Taxonomy::class)) {
            return 'term_' . ($this->term_id ?? null);
        }

        if (is_a($this, User::class)) {
            return 'user_' . ($this->ID ?? null);
        }

        return $this->ID ?? null;
    }
}
