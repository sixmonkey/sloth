<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

/**
 * Backwards-compatible ACF field access.
 *
 * Allows $post->field_name direct access to ACF fields.
 * This behavior is deprecated — use $post->acf->field_name instead.
 *
 * @deprecated Use $post->acf->field_name instead. Will be removed in a future version.
 */
trait HasACF
{
    public function getAttribute($key): mixed
    {
        $value = parent::getAttribute($key);
        if ($value !== null) {
            return $value;
        }

        try {
            $acfValue = $this->acf->{$key};
        } catch (\Throwable) {
            return null;
        }

        if ($acfValue === null) {
            return null;
        }

        trigger_error(
            sprintf(
                'Accessing ACF field "%s" directly on %s is deprecated. '
                . 'Use $model->acf->%s instead.',
                $key,
                static::class,
                $key
            ),
            E_USER_DEPRECATED
        );

        return $acfValue;
    }

    public function __isset($key): bool
    {
        if (parent::__isset($key)) {
            return true;
        }

        try {
            return $this->acf->{$key} !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
