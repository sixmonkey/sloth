<?php

declare(strict_types=1);

namespace Sloth\Field;

use Tbruckmaier\Corcelacf\BaseField;

/**
 * Custom ACF image field that returns a Sloth Image instance.
 *
 * Maps tbruckmaier/corcel-acf's Image field to Sloth's own Image
 * wrapper, preserving the resize() and getThemeSized() API that
 * themes rely on.
 *
 * @since 1.0.0
 */
class AcfImageField extends BaseField
{
    public function getValueAttribute(): ?Image
    {
        $id = $this->internal_value;
        if (!$id) {
            return null;
        }
        return new Image((int) $id);
    }
}
