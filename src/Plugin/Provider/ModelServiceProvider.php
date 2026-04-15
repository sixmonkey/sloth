<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

/**
 * Service provider for model/post type registration.
 *
 * Handles:
 * - Discovery and registration of model classes from DIR_APP/Model/
 * - WordPress post type registration via register_post_type()
 * - Admin column hooks registration
 * - Layotter integration for page builder support
 * - Storing models in container as 'sloth.models'
 *
 * ## Model Discovery
 *
 * Automatically discovers all PHP files in DIR_APP/Model/ and
 * registers them as WordPress post types using their getRegistrationArgs()
 * and registerColumnHooks() methods.
 *
 * ## Container Registration
 *
 * Registers 'sloth.models' in the container as an array mapping
 * post type slugs to their class names. This allows other components
 * to look up model classes by post type.
 *
 * ## Layotter Integration
 *
 * If a model defines $layotter, it will be enabled/disabled for that
 * post type. If $layotter is an array with 'allowed_row_layouts',
 * those layouts will be set for the post type.
 *
 * ## Registration Order
 *
 * Models should be registered after taxonomies so that post types
 * can be associated with taxonomies immediately.
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Layotter\Layotter
 * @see \Sloth\Plugin\Provider\TaxonomyServiceProvider
 */
class ModelServiceProvider
{
    /**
     * Container reference for storing models and Layotter access.
     *
     * @var \Illuminate\Container\Container|null
     */
    protected $container;

    /**
     * Registered models mapping.
     *
     * @var array<string, string>
     */
    protected array $models = [];

    /**
     * Set the container instance.
     *
     * @param \Illuminate\Container\Container $container
     * @since 1.0.0
     *
     */
    public function setContainer($container): void
    {
        $this->container = $container;
    }

    /**
     * Register models from DIR_APP/Model/.
     *
     * Discovers model classes from DIR_APP/Model/, instantiates them,
     * and registers them as WordPress post types.
     *
     * ## Registration Process
     *
     * For each model class found:
     * 1. Load the class file via loadClassFromFile()
     * 2. Skip if $register = false
     * 3. Unregister existing post type if present
     * 4. Register new post type with getRegistrationArgs()
     * 5. Register admin column hooks
     * 6. Configure Layotter integration
     * 7. Flush rewrite rules
     *
     * Note: This must be called on 'init' hook to ensure WordPress
     * rewrite system is initialized.
     *
     * @since 1.0.0
     *
     * @see \Sloth\Model\Model::unregisterExisting() For removing existing post types
     * @see \Sloth\Model\Model::getRegistrationArgs() For registration arguments
     * @see \Sloth\Model\Model::registerColumnHooks() For admin column hooks
     */
    public function register(): void
    {
        add_action('init', function (): void {
            foreach (glob(DIR_APP . 'Model' . DS . '*.php') as $file) {
                $modelName = $this->loadClassFromFile($file);

                $model = new $modelName();
                if (!$model->register) {
                    continue;
                }

                $model->unregisterExisting();
                \register_post_type($model->getPostType(), $model->getRegistrationArgs());
                $model->registerColumnHooks();

                $this->models[$model->getPostType()] = $modelName;

                $this->configureLayotter($model);

                \flush_rewrite_rules(true);
            }

            if ($this->container !== null) {
                $this->container['sloth.models'] = $this->models;
            }
        }, 20);
    }

    /**
     * Configure Layotter integration for a model.
     *
     * Checks the model's $layotter property and enables/disables
     * Layotter for the post type accordingly. If $layotter is an
     * array with 'allowed_row_layouts', those layouts are set.
     *
     * @param object $model The model instance
     * @since 1.0.0
     *
     */
    protected function configureLayotter(object $model): void
    {
        if (!isset($model::$layotter)) {
            return;
        }

        if (is_array($model::$layotter) || $model::$layotter === true) {
            $this->container['layotter']->enable_for_post_type($model->getPostType());
            if (is_array($model::$layotter) && isset($model::$layotter['allowed_row_layouts'])) {
                $this->container['layotter']->set_layouts_for_post_type(
                    $model->getPostType(),
                    $model::$layotter['allowed_row_layouts']
                );
            }
        } else {
            $this->container['layotter']->disable_for_post_type($model->getPostType());
        }
    }

    /**
     * Get all registered models.
     *
     * @return array<string, string> Post type slug to class name mapping
     * @since 1.0.0
     *
     */
    public function getModels(): array
    {
        return $this->models;
    }

    /**
     * Load a class from a file.
     *
     * Includes a PHP file and uses reflection to find the class defined in it.
     * Skips Corcel namespace classes (handled by Corcel itself) and returns
     * the first matching App\ namespaced class.
     *
     * @param string $file Absolute path to the PHP file
     * @return string Class name if found, empty string otherwise
     * @since 1.0.0
     *
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
