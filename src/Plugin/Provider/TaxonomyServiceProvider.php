<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Core\ServiceProvider;
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
 * @since 1.0.0
 * @see \Sloth\Model\Taxonomy
 * @see \Sloth\Plugin\Plugin
 */
class TaxonomyServiceProvider extends ServiceProvider
{
    /**
     * Registered taxonomies mapping.
     *
     * @var array<string, string>
     */
    protected array $taxonomies = [];

    /**
     * Register taxonomies from DIR_APP/Taxonomy/.
     *
     * Discovers taxonomy classes from DIR_APP/Taxonomy/, instantiates them,
     * and registers them with WordPress using getRegistrationArgs() and
     * getPostTypes() methods.
     *
     * @since 1.0.0
     *
     * @see \Sloth\Model\Taxonomy::getRegistrationArgs() For registration arguments
     * @see \Sloth\Model\Taxonomy::getPostTypes() For attached post types
     */
    public function getHooks(): array
    {
        return [
            'init' => [
                ['callback' => fn() => $this->registerTaxonomies(), 'priority' => 20],
                ['callback' => fn() => $this->registerMetaboxes(), 'priority' => 20],
            ],
            'add_meta_boxes' => fn() => $this->addMetaBoxes(),
        ];
    }

    /**
     * Register taxonomies with WordPress.
     *
     * @since 1.0.0
     */
    protected function registerTaxonomies(): void
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

        $this->app['sloth.taxonomies'] = $this->taxonomies;
    }

    /**
     * Register metaboxes for unique taxonomies.
     *
     * @since 1.0.0
     */
    protected function registerMetaboxes(): void
    {
        foreach ($this->taxonomies as $taxonomySlug => $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!$taxonomy->unique) {
                continue;
            }

            foreach ($taxonomy->postTypes as $postType) {
                \remove_meta_box('tagsdiv-' . $taxonomy->getTaxonomy(), $postType, null);
            }
        }
    }

    /**
     * Add metaboxes for unique taxonomies.
     *
     * @since 1.0.0
     */
    protected function addMetaBoxes(): void
    {
        foreach ($this->taxonomies as $taxonomySlug => $taxonomyClass) {
            $taxonomy = new $taxonomyClass();

            if (!$taxonomy->unique) {
                continue;
            }

            $postTypes = $taxonomy->postTypes;

            \add_meta_box(
                'sloth-taxonomy-' . $taxonomy->getTaxonomy(),
                $taxonomy->names['singular'],
                $taxonomy->metabox(...),
                $postTypes,
                'side'
            );
        }
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
     * @since 1.0.0
     *
     * @param string $file Absolute path to the PHP file
     *
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
