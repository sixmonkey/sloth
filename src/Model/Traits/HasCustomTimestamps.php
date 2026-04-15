<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

/**
 * Provides custom timestamp handling for WordPress models.
 *
 * This trait extends Laravel's timestamp behavior to also set the
 * GMT version of timestamp fields (e.g., post_date_gmt alongside post_date).
 * This is important for WordPress compatibility as WordPress stores both
 * local and GMT timestamps.
 *
 * It replaces Corcel's CustomTimestamps trait to maintain control over
 * the model's behavior without depending on Corcel's internal traits.
 *
 * @example
 * ```php
 * class Post extends Model
 * {
 *     use HasCustomTimestamps;
 *
 *     public const CREATED_AT = 'post_date';
 *     public const UPDATED_AT = 'post_modified';
 * }
 * ```
 *
 * @see \Corcel\Concerns\CustomTimestamps Original source
 */
trait HasCustomTimestamps
{
    /**
     * Set the created_at timestamp while also updating the GMT version.
     *
     * When a model is created, both the local timestamp field (e.g., post_date)
     * and its GMT equivalent (e.g., post_date_gmt) are set.
     *
     * @param mixed $value The timestamp value to set
     *
     * @return mixed The result of the parent's setCreatedAt method
     *
     * @example
     * ```php
     * $post = new Post();
     * $post->setCreatedAt('2024-01-15 10:30:00');
     * // Sets both post_date and post_date_gmt
     * ```
     */
    public function setCreatedAt($value)
    {
        // Determine the GMT field name by appending '_gmt' to the CREATED_AT constant
        // e.g., 'post_date' becomes 'post_date_gmt'
        $gmtField = static::CREATED_AT . '_gmt';
        $this->{$gmtField} = $value;

        // Call the parent's setCreatedAt to handle the original field
        return parent::setCreatedAt($value);
    }

    /**
     * Set the updated_at timestamp while also updating the GMT version.
     *
     * When a model is updated, both the local timestamp field (e.g., post_modified)
     * and its GMT equivalent (e.g., post_modified_gmt) are set.
     *
     * @param mixed $value The timestamp value to set
     *
     * @return mixed The result of the parent's setUpdatedAt method
     *
     * @example
     * ```php
     * $post = Post::find(1);
     * $post->setUpdatedAt('2024-01-15 14:45:00');
     * // Sets both post_modified and post_modified_gmt
     * ```
     */
    public function setUpdatedAt($value)
    {
        // Determine the GMT field name by appending '_gmt' to the UPDATED_AT constant
        // e.g., 'post_modified' becomes 'post_modified_gmt'
        $gmtField = static::UPDATED_AT . '_gmt';
        $this->{$gmtField} = $value;

        // Call the parent's setUpdatedAt to handle the original field
        return parent::setUpdatedAt($value);
    }
}
