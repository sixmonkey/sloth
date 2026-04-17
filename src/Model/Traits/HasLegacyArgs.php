<?php

declare(strict_types=1);

namespace Sloth\Model\Traits;

/**
 * Provides backwards-compatible access to legacy model properties.
 *
 * ## Background
 *
 * Prior to the Step 5 refactoring, properties like `$options`, `$names`,
 * `$labels`, `$layotter` etc. were declared directly on the `Model` and
 * `Taxonomy` base classes with no type declarations. Theme developers
 * could override them freely in child classes.
 *
 * PHP 8.4 introduced stricter property inheritance rules: if a parent
 * declares a typed property, child classes must use a compatible type.
 * This would force all theme models to add explicit type declarations —
 * a breaking change.
 *
 * ## Solution
 *
 * This trait removes those properties from the base classes entirely.
 * Instead, it provides a key-value store (`$legacyArgs`) with sensible
 * defaults, and integrates with `Model::__get()` as a fallback:
 *
 * 1. If the theme model declares the property (e.g. `public static $layotter = true`),
 *    Eloquent's `__get()` returns it directly — this trait is never reached.
 * 2. If the theme model does NOT declare the property, `__get()` falls
 *    through to this trait's store, returning the default value.
 *
 * This means theme developers can continue to declare these properties
 * without any type annotation — or omit them entirely to get the default.
 *
 * ## Usage in __get()
 *
 * ```php
 * #[\Override]
 * public function __get($key): mixed
 * {
 *     $value = parent::__get($key);
 *
 *     if ($value === null && $this->hasLegacyArg($key)) {
 *         return $this->getLegacyArg($key);
 *     }
 *
 *     if ($value === null && !property_exists($this, $key)) {
 *         return $this->meta->$key;
 *     }
 *
 *     return $value;
 * }
 * ```
 *
 * ## Theme developer usage
 *
 * ```php
 * // Override a legacy arg — no type declaration needed
 * class Page extends Model {
 *     public static $layotter = true;           // bool
 *     public static $layotter = ['key' => 'v']; // array
 *     protected $options = ['public' => false]; // array
 *     // or omit entirely — default false is returned via __get()
 * }
 * ```
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model::__get() For the fallback integration
 * @see \Sloth\Model\Taxonomy For taxonomy-specific legacy args
 */
trait HasLegacyArgs
{
    /**
     * Default values for legacy model and taxonomy properties.
     *
     * These defaults mirror the original property declarations that existed
     * on the base classes before the PHP 8.4 compatibility refactoring.
     *
     * Keys and their usage:
     * - `names`     — singular/plural display names for post type or taxonomy labels
     * - `options`   — arguments passed to register_post_type() or register_taxonomy()
     * - `labels`    — explicit WordPress label overrides (bypasses auto-generation)
     * - `register`  — whether this model/taxonomy should be registered with WordPress
     * - `icon`      — dashicon name for the post type menu icon (models only)
     * - `postTypes` — which post types this taxonomy is attached to (taxonomies only)
     * - `unique`    — whether this taxonomy allows only one term per post (taxonomies only)
     * - `layotter`  — Layotter integration config: false = disabled, true = enabled
     *                 with defaults, array = custom Layotter configuration (models only)
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    private array $legacyArgs = [
        'names' => [],    // used in models and taxonomies
        'options' => [],    // used in models and taxonomies
        'labels' => [],    // used in models and taxonomies
        'register' => true,  // used in models and taxonomies
        'icon' => null,  // used in models only
        'postTypes' => [],    // used in taxonomies only
        'unique' => false, // used in taxonomies only
        'layotter' => false, // used in models only
    ];

    /**
     * Check whether a key is a known legacy argument.
     *
     * Used by `__get()` to determine whether a null return from Eloquent
     * should fall through to the legacy args store.
     *
     * @param string $key The property name to check.
     * @return bool True if $key is a known legacy argument.
     * @since 1.0.0
     *
     */
    protected function hasLegacyArg(string $key): bool
    {
        return array_key_exists($key, $this->legacyArgs);
    }

    /**
     * Get the value of a legacy argument.
     *
     * Returns the default from `$legacyArgs` unless the theme model
     * declares the property directly — in that case `__get()` returns
     * the theme value before this method is ever called.
     *
     * @param string $key The legacy argument name (e.g. 'layotter', 'options').
     * @return mixed The current value, or the default if not overridden.
     * @since 1.0.0
     *
     */
    protected function getLegacyArg(string $key): mixed
    {
        return $this->legacyArgs[$key];
    }

    /**
     * Set the value of a legacy argument at runtime.
     *
     * Primarily useful for the Registrar or ServiceProviders that need
     * to inject configuration into a model instance after instantiation,
     * without relying on static property declarations.
     *
     * @param string $key The legacy argument name.
     * @param mixed $value The value to store.
     * @since 1.0.0
     *
     */
    protected function setLegacyArg(string $key, mixed $value): void
    {
        $this->legacyArgs[$key] = $value;
    }
}
