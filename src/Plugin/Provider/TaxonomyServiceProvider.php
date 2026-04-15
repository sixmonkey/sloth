<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Facades\Configure;
use Sloth\Utility\Utility;

/**
 * Service provider for taxonomy registration and initialization.
 *
 * Handles:
 * - Discovery and registration of taxonomy classes from DIR_APP/Taxonomy/
 * - WordPress taxonomy registration via register_taxonomy()
 * - Storing taxonomies in container as 'sloth.taxonomies'
 * - Metabox customization for unique (non-hierarchical) taxonomies
 *
 * ## Taxonomy Discovery
 *
 * Automatically discovers all PHP files in DIR_APP/Taxonomy/ and
 * registers them as WordPress taxonomies using their getRegistrationArgs()
 * and getPostTypes() methods.
 *
 * ## Container Registration
 *
 * Registers 'sloth.taxonomies' in the container as an array mapping
 * taxonomy slugs to their class names. This allows other components
 * to look up taxonomy classes by slug.
 *
 * ## Unique Taxonomies
 *
 * For taxonomies with $unique = true, replaces the default tag-style
 * metabox with a custom checklist metabox for better UX.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 * @see \Sloth\Plugin\Plugin
 */
class TaxonomyServiceProvider
{
    /**
     * Container reference for storing taxonomies.
     *
     * @var \Illuminate\Container\Container|null
     */
    protected $container;

    /**
     * Registered taxonomies mapping.
     *
     * @var array<string, string>
     */
    protected array $taxonomies = [];

    /**
     * Set the container instance.
     *
     * @since 1.0.0
     *
     * @param \Illuminate\Container\Container $container
     */
    public function setContainer($container): void
    {
        $this->container = $container;
    }

    /**
     * Register taxonomies from DIR_APP/Taxonomy/.
     *
     * Discovers taxonomy classes from DIR_APP/Taxonomy/, instantiates them,
     * and registers them with WordPress using getRegistrationArgs() and
     * getPostTypes() methods.
     *
     * ## Registration Process
     *
     * For each taxonomy class found:
     * 1. Load the class file via loadClassFromFile()
     * 2. Instantiate the taxonomy
     * 3. Call register_taxonomy() with the taxonomy's arguments
     * 4. Store in $this->taxonomies and container
     *
     * @since 1.0.0
     *
     * @see \Sloth\Model\Taxonomy::getRegistrationArgs() For registration arguments
     * @see \Sloth\Model\Taxonomy::getPostTypes() For attached post types
     */
    public function register(): void
    {
        foreach (glob(DIR_APP . 'Taxonomy' . DS . '*.php') as $file) {
            $taxonomyName = $this->loadClassFromFile($file);
            $taxonomy = new $taxonomyName();
            \register_taxonomy(
                $taxonomy->getTaxonomy(),
                $taxonomy->getPostTypes(),
                $taxonomy->getRegistrationArgs()
            );

            $this->taxonomies[$taxonomy->getTaxonomy()] = $taxonomyName;
        }

        if ($this->container !== null) {
            $this->container['sloth.taxonomies'] = $this->taxonomies;
        }
    }

    /**
     * Initialize taxonomies after registration (admin only).
     *
     * Sets up custom metaboxes for unique (non-hierarchical) taxonomies.
     * This runs on admin_menu hook to ensure taxonomies are registered first.
     *
     * ## Unique Taxonomies
     *
     * For taxonomies with $unique = true, removes the default tag-style
     * metabox and adds a custom category-style checklist metabox.
     *
     * @since 1.0.0
     */
    public function boot(): void
    {
        add_action('admin_menu', function (): void {
            foreach ($this->taxonomies as $taxonomySlug => $taxonomyClass) {
                $taxonomy = new $taxonomyClass();

                if ($taxonomy->unique) {
                    foreach ($taxonomy->postTypes as $postType) {
                        \remove_meta_box('tagsdiv-' . $taxonomy->getTaxonomy(), $postType, null);
                    }

                    $postTypes = $taxonomy->postTypes;

                    add_action('add_meta_boxes', static function () use ($taxonomy, $postTypes): void {
                        \add_meta_box(
                            'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                            $taxonomy->names['singular'],
                            $taxonomy->metabox(...),
                            $postTypes,
                            'side'
                        );
                    });
                }
            }
        }, 20);
    }

    /**
     * Get all registered taxonomies.
     *
     * @since 1.0.0
     *
     * @return array<string, string> Taxonomy slug to class name mapping
     */
    public function getTaxonomies(): array
    {
        return $this->taxonomies;
    }

    /**
     * Load a class from a file.
     *
     * Includes a PHP file and uses reflection to find the class defined in it.
     * Skips Corcel namespace classes (handled by Corcel itself) and returns
     * the first matching App\ namespaced class.
     *
     * @since 1.0.0
     *
     * @param string $file Absolute path to the PHP file
     * @return string Class name if found, empty string otherwise
     */
    protected function loadClassFromFile(string $file): string
    {
        $file = realpath($file);
        include_once $file;

        $matchingClass = null;

        foreach (get_declared_classes() as $class) {
            $rc = new \ReflectionClass($class);
            if ($rc->getFilename() === $file) {
                if (str_starts_with($class, 'Corcel\\')) {
                    continue;
                }

                if (str_starts_with($class, 'App\\')) {
                    $matchingClass = $class;
                    break;
                }

                $matchingClass = $class;
            }
        }

        return $matchingClass ?? '';
    }
}
