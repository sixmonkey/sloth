<?php

declare(strict_types=1);

namespace Sloth\Model;

/**
 * Media Version Model
 *
 * Extends the base Model for handling media version metadata.
 * Media versions store processed image variations in post excerpts as JSON.
 *
 * @since 1.0.0
 * @see Model For the base implementation
 *
 * @property array<string, mixed>|null $options The decoded media version options
 *
 * @example
 * ```php
 * $version = SlothMediaVersion::find($media_version_id);
 * $options = $version->options;
 * ```
 */
class SlothMediaVersion extends Model
{
    /**
     * Gets the media version options from the post excerpt.
     *
     * The options are stored as JSON in the post_excerpt field.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>|null The decoded options or null if invalid JSON
     */
    public function getOptionsAttribute(): ?array
    {
        return json_decode($this->post_excerpt, true);
    }
}
