<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Illuminate\Support\Arr;

/**
 * Provides attribute alias resolution for WordPress compatibility.
 *
 * This trait allows models to expose alternative names for database columns
 * and meta fields, improving the API ergonomics when working with WordPress
 * data structures. It replaces Corcel's Aliases trait with an important fix
 * for the `mutateAttribute()` method that prevents infinite recursion.
 *
 * ## Alias Configuration
 *
 * Define aliases in your model using the `$aliases` static property:
 *
 * @example
 * ```php
 * class Post extends Model
 * {
 *     use HasAliases;
 *
 *     protected static $aliases = [
 *         'title' => 'post_title',        // Simple column alias
 *         'content' => 'post_content',    // Simple column alias
 *         'author' => ['meta' => 'user_id'], // Meta field alias
 *     ];
 * }
 *
 * // Access via alias or original name:
 * $post->title;        // Same as $post->post_title
 * $post->author;       // Same as $post->meta->user_id
 * ```
 *
 * ## Important Fix: mutateAttribute()
 *
 * This trait contains a critical fix for the `mutateAttribute()` method.
 * The original Corcel implementation called `$this->getAttribute()` at the
 * end of the method, which could cause infinite recursion when an alias
 * resolved to a value that triggered another alias lookup.
 *
 * The fix changes the implementation to call `parent::getAttribute()` instead,
 * which goes directly to Laravel's attribute resolution without triggering
 * the alias logic again.
 *
 * @see \Corcel\Concerns\Aliases Original source
 */
trait HasAliases
{
    /**
     * Get all aliases, merging parent and static definitions.
     *
     * Combines the aliases from parent classes with the current class's
     * aliases, allowing for inheritance of alias configurations.
     *
     * @return array The merged aliases array
     *
     * @example
     * ```php
     * $aliases = Post::getAliases();
     * // Returns inherited + local aliases
     * ```
     */
    public static function getAliases(): array
    {
        $aliases = [];

        if (property_exists(static::class, 'aliases')) {
            $aliases = static::$aliases;
        }

        if (property_exists(parent::class, 'aliases')) {
            $parentAliases = parent::$aliases;
            if (is_array($parentAliases)) {
                $aliases = array_merge($parentAliases, $aliases);
            }
        }

        return $aliases;
    }

    /**
     * Add a single alias to the class.
     *
     * Useful for dynamically adding aliases at runtime, for example
     * when loading aliases from configuration.
     *
     * @param string $new The alias name (how it will be accessed)
     * @param string $old The original column or meta field name
     *
     * @example
     * ```php
     * Post::addAlias('headline', 'post_title');
     * // Now $post->headline is equivalent to $post->post_title
     * ```
     */
    public static function addAlias(string $new, string $old): void
    {
        if (!property_exists(static::class, 'aliases')) {
            static::$aliases = [];
        }
        static::$aliases[$new] = $old;
    }

    /**
     * Get an attribute value, resolving aliases if necessary.
     *
     * This method extends Laravel's default attribute resolution to check
     * for aliases when an attribute is not found. If an alias is found,
     * the method resolves it to the original column or meta field.
     *
     * @param string $key The attribute key to retrieve
     *
     * @return mixed The attribute value, or the resolved alias value
     *
     * ## Resolution Order
     *
     * 1. First, try to get the value directly (parent::getAttribute)
     * 2. If null and aliases exist, check if the key is an alias
     * 3. If it's a simple alias (string), get the original column
     * 4. If it's a meta alias (array), get from meta relationship
     * 5. Return the resolved value or null
     *
     * @example
     * ```php
     * // Direct access
     * $post->title;  // Resolves to post_title if 'title' is an alias
     *
     * // With meta alias
     * $post->author; // Resolves to $post->meta->user_id
     * ```
     */
    #[\Override]
    public function getAttribute($key)
    {
        // First, try to get the attribute normally
        $value = parent::getAttribute($key);

        // If value is null and we have aliases, check for alias resolution
        if ($value === null && count(static::getAliases())) {
            // Look up the alias
            if ($value = Arr::get(static::getAliases(), $key)) {
                // Check if it's a meta alias (array format)
                if (is_array($value)) {
                    $metaKey = Arr::get($value, 'meta');

                    // If a meta key is specified, return from meta relationship
                    return $metaKey ? $this->meta->{$metaKey} : null;
                }

                // Simple alias: return the original column value
                return parent::getAttribute($value);
            }
        }

        return $value;
    }

    /**
     * Mutate an attribute value for access.
     *
     * This method is called by Laravel when accessing an attribute that
     * has a defined get mutator. It ensures that alias resolution happens
     * correctly before applying the mutator.
     *
     * ## Critical Fix: No Recursion
     *
     * IMPORTANT: This method MUST NOT call `$this->getAttribute()` at the end.
     * The original Corcel implementation did this, which caused infinite
     * recursion when:
     *
     * 1. An attribute is accessed that doesn't exist
     * 2. It resolves to an alias
     * 3. The alias triggers another attribute access
     * 4. Which triggers another alias resolution
     * 5. ...infinite loop
     *
     * The fix is to call `parent::getAttribute()` directly, which bypasses
     * the alias logic and goes straight to Laravel's attribute resolution.
     *
     * @param string $key The attribute key being accessed
     * @param mixed $value The current value of the attribute
     *
     * @return mixed The mutated value
     *
     * @example
     * ```php
     * // When accessing an attribute with a mutator:
     * // 1. Check if the key has a get mutator
     * // 2. If yes, call the mutator with the resolved value
     * // 3. Return the mutated result
     * ```
     */
    #[\Override]
    public function mutateAttribute($key, $value)
    {
        // Check if this attribute has a custom get mutator
        if ($this->hasGetMutator($key)) {
            // Let the parent handle mutator application
            return parent::mutateAttribute($key, $value);
        }

        // FIXED: Call parent::getAttribute() instead of $this->getAttribute()
        //
        // This prevents infinite recursion that occurred in Corcel's original
        // implementation. When $this->getAttribute() was called, it would:
        // 1. Try to get the attribute value
        // 2. Check if it's an alias and resolve it
        // 3. If the resolved value triggers another lookup...
        //
        // By calling parent::getAttribute() directly, we bypass the alias
        // resolution logic and go straight to Laravel's native attribute
        // resolution, which is safe from recursion.
        return parent::getAttribute($key);
    }
}
