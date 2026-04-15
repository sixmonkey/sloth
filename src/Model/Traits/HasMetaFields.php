<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

use Corcel\Model\Meta\CommentMeta;
use Corcel\Model\Meta\PostMeta;
use Corcel\Model\Meta\TermMeta;
use Corcel\Model\Meta\ThumbnailMeta;
use Corcel\Model\Meta\UserMeta;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use UnexpectedValueException;

/**
 * Provides WordPress meta field management capabilities.
 *
 * This trait enables models to interact with WordPress post meta, user meta,
 * term meta, and comment meta through Laravel's Eloquent relationship system.
 * It replaces Corcel's MetaFields trait to maintain control over the model's
 * behavior while adding support for Sloth-specific model classes.
 *
 * ## Meta Class Mapping
 *
 * The trait automatically determines which meta class to use based on the
 * model class hierarchy. It supports both Corcel's native models and Sloth's
 * custom models:
 *
 * | Model Class | Meta Class |
 * |-------------|------------|
 * | Corcel\Model\Post | PostMeta |
 * | Sloth\Model\Model | PostMeta |
 * | Corcel\Model\User | UserMeta |
 * | Sloth\Model\User | UserMeta |
 * | Corcel\Model\Term | TermMeta |
 * | Sloth\Model\Taxonomy | TermMeta |
 * | Corcel\Model\Comment | CommentMeta |
 *
 * ## Usage
 *
 * @example
 * ```php
 * class Post extends Model
 * {
 *     use HasMetaFields;
 * }
 *
 * // Access meta directly
 * $value = $post->meta->field_name;
 *
 * // Use meta relationship
 * $allMeta = $post->meta()->get();
 *
 * // Save meta field
 * $post->saveMeta('custom_field', 'value');
 *
 * // Query by meta
 * $posts = Post::hasMeta('featured', true)->get();
 * ```
 *
 * @see \Corcel\Concerns\MetaFields Original source
 * @see https://developer.wordpress.org/plugins/metadata/ WordPress Meta API
 */
trait HasMetaFields
{
    /**
     * Mapping of model classes to their corresponding meta classes.
     *
     * This array determines which Eloquent model should be used when
     * accessing meta fields for a given model. Both Corcel's native
     * models and Sloth's custom models are supported.
     *
     * @var array<class-string, class-string>
     *
     * @example
     * ```php
     * // When accessing $post->meta, the trait looks up the model class
     * // in this array to determine PostMeta::class should be used
     * ```
     */
    protected static array $metaClassMap = [
        CommentMeta::class => CommentMeta::class,
        PostMeta::class => PostMeta::class,
        TermMeta::class => TermMeta::class,
        UserMeta::class => UserMeta::class,
    ];

    /**
     * Alternative names for accessing meta relationships.
     *
     * This allows using `fields()` as an alias for `meta()`,
     * providing a more semantic API for content fields.
     *
     * @return HasMany The meta relationship
     *
     * @example
     * ```php
     * // Both of these are equivalent:
     * $post->meta->custom_field;
     * $post->fields->custom_field;
     * ```
     */
    public function fields(): HasMany
    {
        return $this->meta();
    }

    /**
     * Get the meta relationship for this model.
     *
     * Returns a HasMany relationship to the corresponding meta model
     * (PostMeta, UserMeta, TermMeta, or CommentMeta) based on the
     * model class hierarchy.
     *
     * @return HasMany The meta relationship
     *
     * @throws UnexpectedValueException If the model doesn't extend a known Corcel model
     *
     * @example
     * ```php
     * $post = Post::find(1);
     * foreach ($post->meta as $meta) {
     *     echo $meta->meta_key . ': ' . $meta->meta_value;
     * }
     * ```
     */
    public function meta(): HasMany
    {
        return $this->hasMany($this->getMetaClass(), $this->getMetaForeignKey());
    }

    /**
     * Determines the appropriate meta class for this model.
     *
     * This method iterates through the built-in class mapping to find
     * which Corcel model the current model extends, then returns
     * the corresponding meta class.
     *
     * @return string The fully qualified class name of the meta model
     *
     * @throws UnexpectedValueException If the model doesn't extend a known Corcel model
     *
     * @example
     * ```php
     * $metaClass = $post->getMetaClass();
     * // Returns: Corcel\Model\Meta\PostMeta::class
     * ```
     */
    protected function getMetaClass(): string
    {
        // Define the mapping of model classes to their meta classes
        // This includes both Corcel's native models and Sloth's custom models
        $builtInClasses = [
            \Corcel\Model\Comment::class => CommentMeta::class,
            \Corcel\Model\Post::class => PostMeta::class,
            \Corcel\Model\Term::class => TermMeta::class,
            \Corcel\Model\User::class => UserMeta::class,
            // Sloth-specific mappings
            \Sloth\Model\Model::class => PostMeta::class,
            \Sloth\Model\User::class => UserMeta::class,
            \Sloth\Model\Taxonomy::class => TermMeta::class,
        ];

        foreach ($builtInClasses as $model => $meta) {
            if ($this instanceof $model) {
                return $meta;
            }
        }

        throw new UnexpectedValueException(sprintf(
            '%s must extend one of Corcel built-in models: Comment, Post, Term or User, ' .
            'or a Sloth model: Sloth\Model\Model, Sloth\Model\User, Sloth\Model\Taxonomy.',
            static::class
        ));
    }

    /**
     * Determines the foreign key name for the meta relationship.
     *
     * Returns the foreign key that should be used when querying the
     * meta table (e.g., 'post_id', 'user_id', 'term_id', 'comment_id').
     *
     * @return string The foreign key name without '_id' suffix
     *
     * @throws UnexpectedValueException If the model doesn't extend a known Corcel model
     *
     * @example
     * ```php
     * $foreignKey = $post->getMetaForeignKey();
     * // Returns: 'post_id'
     * ```
     */
    protected function getMetaForeignKey(): string
    {
        // Define the mapping of model classes to their foreign key names
        // The foreign key is derived from the lowercase model class name + '_id'
        $builtInClasses = [
            \Corcel\Model\Comment::class => 'comment_id',
            \Corcel\Model\Post::class => 'post_id',
            \Corcel\Model\Term::class => 'term_id',
            \Corcel\Model\User::class => 'user_id',
            // Sloth-specific mappings
            \Sloth\Model\Model::class => 'post_id',
            \Sloth\Model\User::class => 'user_id',
            \Sloth\Model\Taxonomy::class => 'term_id',
        ];

        foreach ($builtInClasses as $model => $foreignKey) {
            if ($this instanceof $model) {
                return $foreignKey;
            }
        }

        throw new UnexpectedValueException(sprintf(
            '%s must extend one of Corcel built-in models: Comment, Post, Term or User, ' .
            'or a Sloth model: Sloth\Model\Model, Sloth\Model\User, Sloth\Model\Taxonomy.',
            static::class
        ));
    }

    /**
     * Scope to filter records by meta field existence and value.
     *
     * Allows querying models based on their meta field values using
     * Laravel's query builder.
     *
     * @param Builder $query The query builder instance
     * @param string|array $meta The meta key to filter by, or an array of key => value pairs
     * @param mixed $value The expected value (optional, use null for existence check)
     * @param string $operator The comparison operator (default: '=')
     *
     * @return Builder The modified query builder
     *
     * @example
     * ```php
     * // Find posts with a specific featured flag
     * $featured = Post::hasMeta('featured', true)->get();
     *
     * // Find posts where custom_field exists (any value)
     * $withCustom = Post::hasMeta('custom_field')->get();
     *
     * // Find posts with multiple meta conditions
     * $results = Post::hasMeta([
     *     'status' => 'published',
     *     'featured' => true,
     * ])->get();
     * ```
     */
    public function scopeHasMeta(Builder $query, $meta, $value = null, string $operator = '='): Builder
    {
        // Normalize input: if a single key is passed, convert to array format
        if (!is_array($meta)) {
            $meta = [$meta => $value];
        }

        // Apply each meta condition to the query
        foreach ($meta as $key => $value) {
            $query->whereHas('meta', function (Builder $query) use ($key, $value, $operator) {
                // If the key is not a string, assume we're checking for existence
                // and the value is actually the operator
                if (!is_string($key)) {
                    return $query->where('meta_key', $operator, $value);
                }

                // Filter by meta key
                $query->where('meta_key', $operator, $key);

                // If value is null, only check for key existence
                // Otherwise, also filter by meta value
                return is_null($value) ? $query : $query->where('meta_value', $operator, $value);
            });
        }

        return $query;
    }

    /**
     * Scope to filter records where meta value contains a string (LIKE query).
     *
     * Convenience method for text searches within meta values.
     *
     * @param Builder $query The query builder instance
     * @param string $meta The meta key to search
     * @param mixed $value The value pattern to search for
     *
     * @return Builder The modified query builder
     *
     * @example
     * ```php
     * // Find posts where the title meta contains "important"
     * $important = Post::hasMetaLike('title', '%important%')->get();
     * ```
     */
    public function scopeHasMetaLike(Builder $query, $meta, $value = null): Builder
    {
        return $this->scopeHasMeta($query, $meta, $value, 'like');
    }

    /**
     * Save a single meta field.
     *
     * @param string $key The meta key
     * @param mixed $value The value to save
     *
     * @return bool True on success, false on failure
     *
     * @example
     * ```php
     * $post->saveMeta('views', 100);
     * ```
     */
    public function saveField($key, $value): bool
    {
        return $this->saveMeta($key, $value);
    }

    /**
     * Save one or more meta fields.
     *
     * Can save a single key-value pair or multiple fields at once.
     * When saving multiple fields, the meta relationship is reloaded
     * after all saves complete.
     *
     * @param string|array $key The meta key to save, or an array of key => value pairs
     * @param mixed $value The value to save (ignored if $key is an array)
     *
     * @return bool True on success, false on failure
     *
     * @example
     * ```php
     * // Save single field
     * $post->saveMeta('author', 'John Doe');
     *
     * // Save multiple fields
     * $post->saveMeta([
     *     'author' => 'John Doe',
     *     'source' => 'API',
     * ]);
     * ```
     */
    public function saveMeta($key, $value = null): bool
    {
        // Handle array input: save multiple fields at once
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->saveOneMeta($k, $v);
            }
            // Reload meta relationship to reflect changes
            $this->load('meta');

            return true;
        }

        // Handle single field save
        return $this->saveOneMeta($key, $value);
    }

    /**
     * Save a single meta field value.
     *
     * Internal method that handles the actual save operation for
     * one meta field.
     *
     * @param string $key The meta key
     * @param mixed $value The value to save
     *
     * @return bool True on success, false on failure
     *
     * @internal
     */
    private function saveOneMeta(string $key, mixed $value): bool
    {
        // Find existing meta record or create a new one
        $meta = $this->meta()
            ->where('meta_key', $key)
            ->firstOrNew(['meta_key' => $key]);

        // Save the value and reload the relationship
        $result = $meta->fill(['meta_value' => $value])->save();
        $this->load('meta');

        return $result;
    }

    /**
     * Create a new meta field.
     *
     * Similar to saveMeta, but always creates a new record
     * even if the key already exists.
     *
     * @param string $key The meta key
     * @param mixed $value The value to store
     *
     * @return \Illuminate\Database\Eloquent\Model The created meta model
     *
     * @example
     * ```php
     * $meta = $post->createMeta('tracking_id', 'ABC123');
     * ```
     */
    public function createField($key, $value)
    {
        return $this->createMeta($key, $value);
    }

    /**
     * Create one or more new meta fields.
     *
     * Always creates new records, even if the key already exists.
     * Returns a Collection if multiple fields are created.
     *
     * @param string|array $key The meta key(s) to create
     * @param mixed $value The value(s) to store (ignored if $key is an array)
     *
     * @return \Illuminate\Database\Eloquent\Model|\Illuminate\Support\Collection
     *
     * @example
     * ```php
     * // Create single meta
     * $meta = $post->createMeta('sku', 'PROD-001');
     *
     * // Create multiple meta
     * $metas = $post->createMeta([
     *     'sku' => 'PROD-001',
     *     'ean' => '1234567890123',
     * ]);
     * ```
     */
    public function createMeta($key, $value = null)
    {
        // Handle array input: create multiple fields
        if (is_array($key)) {
            return collect($key)->map(function ($value, $key) {
                return $this->createOneMeta($key, $value);
            });
        }

        // Handle single field creation
        return $this->createOneMeta($key, $value);
    }

    /**
     * Create a single new meta field.
     *
     * Internal method that handles the actual creation of one meta record.
     *
     * @param string $key The meta key
     * @param mixed $value The value to store
     *
     * @return \Illuminate\Database\Eloquent\Model The created meta model
     *
     * @internal
     */
    private function createOneMeta(string $key, mixed $value)
    {
        // Create new meta record
        $meta = $this->meta()->create([
            'meta_key' => $key,
            'meta_value' => $value,
        ]);

        // Reload meta relationship to reflect changes
        $this->load('meta');

        return $meta;
    }

    /**
     * Get a specific meta value by key.
     *
     * Retrieves a single meta field's value from the loaded meta
     * relationship. This is a convenience method for accessing
     * meta values without manually iterating through the relationship.
     *
     * @param string $attribute The meta key to retrieve
     *
     * @return mixed The meta value, or null if not found
     *
     * @example
     * ```php
     * $title = $post->getMeta('title');
     * $views = $post->getMeta('views') ?? 0;
     * ```
     */
    #[\ReturnTypeWillChange]
    public function getMeta($attribute)
    {
        // Access the meta relationship using the key as property name
        if ($meta = $this->meta->{$attribute}) {
            return $meta;
        }

        return null;
    }
}
