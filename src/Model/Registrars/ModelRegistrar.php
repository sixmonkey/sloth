<?php

declare(strict_types=1);

namespace Sloth\Model\Registrars;

use Sloth\Core\Application;
use Sloth\Model\Resolvers\ModelsResolver;

/**
 * Registrar for model/post type registration.
 *
 * Handles:
 * - Discovery and registration of model classes from DIR_APP/Model/
 * - WordPress post type registration via register_post_type()
 * - Admin column hooks registration
 * - Layotter integration for page builder support
 * - Storing models in container as 'sloth.models'
 *
 * @since 1.0.0
 * @see \Sloth\Model\Model
 * @see \Sloth\Layotter\Layotter
 * @see \Sloth\Model\Registrars\TaxonomyRegistrar
 */
class ModelRegistrar
{
    /**
     * Registered models mapping.
     *
     * @var array<string, string>
     */
    protected array $models = [];

    /**
     * The application instance.
     *
     * @var Application|mixed|null
     */
    private ?Application $app;

    /**
     * Constructor for ModelRegistrar.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->app = app();
    }

    /**
     * Initialize the model registrar.
     *
     * Discovers and registers all models with WordPress.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $this->registerModels();
    }

    /**
     * Register models with WordPress.
     *
     * @since 1.0.0
     */
    protected function registerModels(): void
    {
        $models = [];
        ModelsResolver::resolve()->each(function (string $modelName) use ($models): void {
            $model = new $modelName();
            if (!$model->register) {
                return;
            }

            $model->unregisterExisting();
            \register_post_type($model->getPostType(), $model->getRegistrationArgs());
            $model->registerColumnHooks();

            $models[$model->getPostType()] = $modelName;

            $this->configureLayotter($model);

            \flush_rewrite_rules(true);
        });

        $this->models = $models;
        $this->app['sloth.models'] = $this->models;
    }

    /**
     * Configure Layotter integration for a model.
     *
     * @param object $model The model instance
     *
     * @since 1.0.0
     */
    protected function configureLayotter(object $model): void
    {
        if (!isset($model::$layotter)) {
            return;
        }

        if (is_array($model::$layotter) || $model::$layotter === true) {
            $this->app['layotter']->enable_for_post_type($model->getPostType());
            if (is_array($model::$layotter) && isset($model::$layotter['allowed_row_layouts'])) {
                $this->app['layotter']->set_layouts_for_post_type(
                    $model->getPostType(),
                    $model::$layotter['allowed_row_layouts']
                );
            }
        } else {
            $this->app['layotter']->disable_for_post_type($model->getPostType());
        }
    }

    /**
     * Load a class from a file.
     *
     * @param string $file Absolute path to the PHP file
     *
     * @return string Class name if found, empty string otherwise
     *
     * @since 1.0.0
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
