<?php

declare(strict_types=1);

namespace Sloth\Model;

use PostTypes\Columns;
use PostTypes\Contracts\PostTypeContract;

/**
 * Adapter for PostTypes v3 compatibility.
 *
 * This class provides the method-based API required by PostTypes v3
 * while reading configuration from the v2-style properties used in Sloth models.
 *
 * Models should use this trait and keep their existing property definitions.
 *
 * @since 1.0.0
 */
trait PostTypeAdapter
{
    /**
     * Get the post type name (slug).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function name(): string
    {
        $postTypeName = $this->getPostType();
        return $postTypeName ?: ($this->names['name'] ?? '');
    }

    /**
     * Get the post type slug.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string
    {
        return $this->names['slug'] ?? $this->name();
    }

    /**
     * Get the post type labels.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function labels(): array
    {
        $labels = $this->labels ?? [];

        if (!empty($labels)) {
            if (is_array($labels) && count($labels) > 0) {
                foreach ($labels as $key => $label) {
                    if (is_string($label)) {
                        $labels[$key] = \__($label);
                    }
                }
            }
            return $labels;
        }

        $singular = $this->names['singular'] ?? ucfirst($this->name());
        $plural = $this->names['plural'] ?? $singular . 's';

        return [
            'name' => __($plural),
            'singular_name' => __($singular),
            'add_new' => __('Add New'),
            'add_new_item' => sprintf(__('Add New %s'), __($singular)),
            'edit_item' => sprintf(__('Edit %s'), __($singular)),
            'new_item' => sprintf(__('New %s'), __($singular)),
            'view_item' => sprintf(__('View %s'), __($singular)),
            'view_items' => sprintf(__('View %s'), __($plural)),
            'search_items' => sprintf(__('Search %s'), __($plural)),
            'not_found' => sprintf(__('No %s found'), __($plural)),
            'not_found_in_trash' => sprintf(__('No %s found in Trash'), __($plural)),
            'parent_item_colon' => sprintf(__('Parent %s:'), __($singular)),
            'all_items' => sprintf(__('All %s'), __($plural)),
            'archives' => sprintf(__('%s Archives'), __($singular)),
            'attributes' => sprintf(__('%s Attributes'), __($singular)),
            'insert_into_item' => sprintf(__('Insert into %s'), __($singular)),
            'uploaded_to_this_item' => sprintf(__('Uploaded to this %s'), __($singular)),
            'filter_items_list' => sprintf(__('Filter %s list'), __($plural)),
            'filter_by_date' => __('Filter by date'),
            'items_list_navigation' => sprintf(__('%s list navigation'), __($plural)),
            'items_list' => sprintf(__('%s list'), __($plural)),
            'item_published' => sprintf(__('%s published'), __($singular)),
            'item_published_privately' => sprintf(__('%s published privately'), __($singular)),
            'item_reverted_to_draft' => sprintf(__('%s reverted to draft'), __($singular)),
            'item_scheduled' => sprintf(__('%s scheduled'), __($singular)),
            'item_updated' => sprintf(__('%s updated'), __($singular)),
            'menu_name' => __($plural),
        ];
    }

    /**
     * Get the post type options.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $options = $this->options ?? [];

        if (isset($this->icon)) {
            $options['menu_icon'] = 'dashicons-' . preg_replace('/^dashicons-/', '', $this->icon);
        }

        if (!isset($options['labels'])) {
            $options['labels'] = $this->labels();
        }

        return $options;
    }

    /**
     * Get associated taxonomies.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function taxonomies(): array
    {
        return $this->taxonomies ?? [];
    }

    /**
     * Get supported features.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function supports(): array
    {
        if (isset($this->options['supports'])) {
            return is_array($this->options['supports']) ? $this->options['supports'] : [];
        }

        return ['title', 'editor'];
    }

    /**
     * Get the menu icon.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function icon(): ?string
    {
        return $this->icon ? 'dashicons-' . preg_replace('/^dashicons-/', '', $this->icon) : null;
    }

    /**
     * Get filters for admin list table.
     *
     * @since 1.0.0
     *
     * @return array<string>
     */
    public function filters(): array
    {
        return $this->filters ?? [];
    }

    /**
     * Configure columns for the admin list table.
     *
     * @since 1.0.0
     *
     * @param Columns $columns
     *
     * @return Columns
     */
    public function configureColumnsFromAdapter(Columns $columns): Columns
    {
        $hiddenColumns = $this->admin_columns_hidden ?? [];
        if (!empty($hiddenColumns)) {
            $columns->remove($hiddenColumns);
        }

        $addedColumns = $this->admin_columns ?? [];
        if (!empty($addedColumns)) {
            foreach ($addedColumns as $key => $label) {
                $columns->add($key)->label($label);
            }
        }

        $class = static::class;
        foreach (array_keys($addedColumns) as $key) {
            $columns->populate($key, static function ($postId) use ($class, $key): void {
                $model = $class::find($postId);
                if ($model) {
                    if (method_exists($model, 'getColumn')) {
                        echo $model->getColumn($key);
                    } else {
                        $value = $model->{$key} ?? '';
                        echo \esc_html((string) $value);
                    }
                }
            });
        }

        $sortable = [];
        foreach (array_keys($addedColumns) as $key) {
            $sortable[$key] = $key;
        }
        $columns->sort($sortable);

        return $columns;
    }

    /**
     * Register the post type with WordPress.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        global $wp_post_types;

        $postTypeName = $this->name();

        if (!$postTypeName || $postTypeName === '') {
            return;
        }

        if (\post_type_exists($postTypeName)) {
            $post_type_object = \get_post_type_object($postTypeName);
            if ($post_type_object) {
                $post_type_object->remove_supports();
                $post_type_object->remove_rewrite_rules();
                $post_type_object->unregister_meta_boxes();
                $post_type_object->remove_hooks();
                $post_type_object->unregister_taxonomies();

                unset($wp_post_types[$postTypeName]);
                \do_action('unregistered_post_type', $postTypeName);
            }
        }

        \register_post_type($postTypeName, $this->options());

        if (!empty($this->taxonomies())) {
            foreach ($this->taxonomies() as $taxonomy) {
                \register_taxonomy_for_object_type($taxonomy, $postTypeName);
            }
        }

        if (!empty($this->filters())) {
            foreach ($this->filters() as $taxonomy) {
                if (\taxonomy_exists($taxonomy)) {
                    \register_taxonomy_for_object_type($taxonomy, $postTypeName);
                }
            }
        }
    }
}
