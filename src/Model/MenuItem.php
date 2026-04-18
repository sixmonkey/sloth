<?php

declare(strict_types=1);

namespace Sloth\Model;

use Corcel\Model\Meta\PostMeta;
use Illuminate\Support\Arr;
use Corcel\Model\Page;
use Corcel\Model\CustomLink;
use Corcel\Model\Taxonomy;
use Corcel\Model\MenuItem as Corcel;
use Corcel\Model as CorcelModel;

/**
 * Menu Item Model
 *
 * Represents a WordPress navigation menu item with support for
 * various item types (posts, pages, categories, custom links).
 *
 * @since 1.0.0
 * @extends Model<\Corcel\Model\Post>
 *
 * @property string $url The menu item URL
 * @property string $title The menu item title
 * @property bool $current Whether this item is the current page
 * @property bool $current_item_parent Whether this is a parent of current item
 * @property bool $current_item_ancestor Whether this is an ancestor of current item
 * @property bool $in_current_path Whether in current path
 * @property string $classes The menu item CSS classes
 *
 * @example
 * ```php
 * // Get menu by location
 * $menu = Menu::location('primary');
 *
 * // Iterate through items
 * foreach ($menu->items as $item) {
 *     echo $item->title;
 *     echo $item->url;
 *     echo $item->current ? 'active' : '';
 * }
 * ```
 */
class MenuItem extends Corcel
{
    /**
     * Creates a new MenuItem instance.
     *
     * @param array<string, mixed> $attributes Initial attributes
     *
     * @throws \ReflectionException If class reflection fails
     * @since 1.0.0
     *
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->instanceRelations = array_merge(
            app('sloth.models') ?? [],
            $this->instanceRelations
        );

        $this->instanceRelations = array_merge(
            app('sloth.taxonomies') ?? [],
            $this->instanceRelations
        );
    }

    /**
     * Gets the WordPress menu item post with processed classes.
     *
     * @return \WP_Post The processed menu item post
     *
     * @since 1.0.0
     *
     * @uses wp_setup_nav_menu_item() To process the menu item
     * @uses _wp_menu_item_classes_by_context() To add classes
     */
    private function get_wp_post_classes(): \WP_Post
    {
        $post = \wp_setup_nav_menu_item(\get_post($this->meta->_menu_item_object_id));

        $items = [$post];
        \_wp_menu_item_classes_by_context($items);

        return reset($items);
    }

    /**
     * Gets the parent menu item.
     *
     * @return CustomLink|Model|CorcelModel|null The parent item or null
     * @since 1.0.0
     */
    #[\Override]
    public function parent(): CustomLink|Model|CorcelModel|null
    {
        $className = $this->getClassName();

        if ($className) {
            return new $className()->newQuery()
                ->find($this->meta->_menu_item_menu_item_parent);
        }

        return null;
    }

    /**
     * Gets the instance (actual post/page/term) this menu item links to.
     *
     * @return CustomLink|Model|CorcelModel|null The instance or null
     * @since 1.0.0
     */
    #[\Override]
    public function instance(): CustomLink|Model|CorcelModel|null
    {
        $className = $this->getClassName();

        if ($className) {
            return new $className()->newQuery()
                ->find($this->meta->_menu_item_object_id);
        }

        return null;
    }

    /**
     * Gets the class name for the menu item type.
     *
     * @return class-string|null The fully qualified class name or null
     * @since 1.0.0
     *
     */
    #[\Override]
    protected function getClassName(): ?string
    {
        return Arr::get($this->instanceRelations, $this->meta->_menu_item_object);
    }

    /**
     * Gets the URL for this menu item.
     *
     * @return string|\WP_Error The URL or error
     * @since 1.0.0
     *
     */
    public function getUrlAttribute(): string|\WP_Error
    {
        return match ($this->_menu_item_type) {
            'taxonomy' => $this->get_taxonomy_url(),
            'custom' => $this->_menu_item_url,
            'post_type_archive' => \get_post_type_archive_link($this->_menu_item_object),
            'post_type' => \get_permalink($this->instance()->ID ?? 0),
            default => '',
        };
    }

    /**
     * Gets the taxonomy URL for taxonomy-type menu items.
     *
     * @return string|\WP_Error The term link or error
     * @since 1.0.0
     *
     */
    private function get_taxonomy_url(): string|\WP_Error
    {
        $tax = $this->instance()->toArray();

        return \get_term_link((int) ($tax['term_taxonomy_id'] ?? 0), $tax['taxonomy'] ?? '');
    }

    /**
     * Gets the title for this menu item.
     *
     * Falls back to the linked post/term title if no custom title is set.
     *
     * @return string The menu item title
     * @since 1.0.0
     *
     */
    public function getTitleAttribute(): string
    {
        if ($this->post_title) {
            return $this->post_title;
        }

        $instance = $this->instance();

        if (is_object($instance) && $instance->post_title) {
            return $instance->post_title;
        }

        return match ($this->_menu_item_type) {
            'taxonomy' => $this->get_taxonomy_title(),
            'post_type_archive' => $this->get_archive_title(),
            default => is_object($instance) ? ($instance->post_title ?? '') : ($instance['post_title'] ?? ''),
        };
    }

    /**
     * Gets the taxonomy name for taxonomy-type items.
     *
     * @return string The taxonomy term name
     * @since 1.0.0
     *
     */
    private function get_taxonomy_title(): string
    {
        $tax = $this->instance()->toArray();

        return (string) \get_term_field('name', (int) ($tax['term_taxonomy_id'] ?? 0), $tax['taxonomy'] ?? '', 'raw');
    }

    /**
     * Gets the post type archive title.
     *
     * @return string The archive title
     * @since 1.0.0
     *
     */
    private function get_archive_title(): string
    {
        $obj = get_post_type_object($this->_menu_item_object);

        return $obj->labels->name ?? '';
    }

    /**
     * Gets the current state of this menu item.
     *
     * @return bool Whether this is the current page
     * @since 1.0.0
     *
     */
    public function getCurrentAttribute(): bool
    {
        $post = $this->get_wp_post_classes();

        return (bool) ($post->current ?? false);
    }

    /**
     * Gets whether this is a parent of the current item.
     *
     * @return bool True if this is a parent of current item
     * @since 1.0.0
     *
     */
    public function getCurrentItemParentAttribute(): bool
    {
        $context = app('sloth.context') ?? [];

        if (isset($context['post'])) {
            $instance = $this->instance();
            $id = is_object($instance) ? ($instance->ID ?? 0) : ($instance['ID'] ?? 0);

            if ((int) $context['post']->parent_id === $id) {
                return true;
            }

            if ($this->_menu_item_type === 'post_type_archive'
                && $context['post']->postType === $this->_menu_item_object) {
                return true;
            }

            $option_key = 'link_overview_' . $context['post']->postType;
            if (get_option($option_key)) {
                return (int) get_option($option_key) === $id;
            }
        }

        $post = $this->get_wp_post_classes();

        return (bool) ($post->current_item_parent ?? false);
    }

    /**
     * Gets whether this is an ancestor of the current item.
     *
     * @return bool Whether this is an ancestor
     * @since 1.0.0
     *
     */
    public function getCurrentItemAncestorAttribute(): bool
    {
        $post = $this->get_wp_post_classes();

        return (bool) ($post->current_item_ancestor ?? false);
    }

    /**
     * Gets whether this item is in the current path.
     *
     * @return bool True if current or parent of current
     * @since 1.0.0
     *
     */
    public function getInCurrentPathAttribute(): bool
    {
        if ($this->getCurrentAttribute()) {
            return true;
        }

        return $this->getCurrentItemParentAttribute();
    }

    /**
     * Gets the CSS classes for this menu item.
     *
     * @return string Space-separated CSS classes
     * @since 1.0.0
     *
     */
    public function getClassesAttribute(): string
    {
        $post = $this->get_wp_post_classes();
        $classes = $post->classes ?? [];

        if ($post->current ?? false) {
            $classes[] = 'current';
            $classes[] = 'active';
        }

        if ($post->current_item_parent ?? false) {
            $classes[] = 'current_item_parent';
        }

        if ($post->current_item_ancestor ?? false) {
            $classes[] = 'current_item_ancestor';
        }

        return trim(implode(' ', array_filter((array) $classes)));
    }

    /**
     * Gets the child menu items.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany The children relationship
     * @since 1.0.0
     *
     */
    #[\Override]
    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasManyThrough(
            MenuItem::class,
            PostMeta::class,
            'meta_value',
            'ID',
            'ID',
            'post_id'
        )->where('ID', '!=', $this->ID);
    }
}
