<?php

declare(strict_types=1);

namespace Sloth\Module\Registrars;

use Sloth\Core\Application;
use Sloth\Module\Resolvers\ModulesResolver;
use Sloth\Utility\Utility;

/**
 * Service provider for module discovery and registration.
 *
 * Handles:
 * - Discovery of module classes from get_template_directory()/Module/
 * - Layotter element registration for page builder integration
 * - JSON/AJAX endpoint registration for module data
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module
 * @see \Sloth\Module\LayotterElement
 * @see \Sloth\Plugin\Plugin
 */
class ModuleRegistrar
{
    /**
     * Registered module class names.
     *
     * @var array<int, string>
     */
    protected array $modules = [];

    protected ?Application $app;

    public function __construct()
    {
        $this->app = app();
    }

    /**
     * Boot the provider.
     *
     * Dynamic hooks per module - getHooks() cannot return these
     * because module discovery must happen first.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        ModulesResolver::resolve()->each(function ($moduleClass) {
            $this->registerJsonEndpoints($moduleClass);
            $this->modules[] = $moduleClass;
        });
        
        $this->registerLayotterElements();
    }

    /**
     * Register all Layotter elements.
     *
     * @since 1.0.0
     */
    protected function registerLayotterElements(): void
    {
        foreach ($this->modules as $moduleName) {
            $this->registerLayotterElement($moduleName);
        }
    }

    /**
     * Register a module as a Layotter element.
     *
     * @param string $moduleName The fully qualified module class name
     *
     * @since 1.0.0
     */
    protected function registerLayotterElement(string $moduleName): void
    {
        if (!is_array($moduleName::$layotter) || !class_exists('\\Layotter')) {
            return;
        }

        $className = substr(strrchr($moduleName, '\\'), 1);
        $moduleClassName = $moduleName;

        eval("class {$className} extends \\Sloth\\Module\\LayotterElement {
				static \$module = '{$moduleClassName}';
			}");
        \Layotter::register_element(strtolower(substr(strrchr($moduleName, '\\'), 1)), $className);
    }

    /**
     * Register JSON/AJAX endpoints for a module.
     *
     * @param string $moduleName The fully qualified module class name
     *
     * @since 1.0.0
     */
    protected function registerJsonEndpoints(string $moduleName): void
    {
        if (!$moduleName::$json) {
            return;
        }

        $m = new $moduleName();

        add_action('wp_ajax_nopriv_' . $m->getAjaxAction(), [new $moduleName(), 'getJSON']);
        add_action('wp_ajax_' . $m->getAjaxAction(), [new $moduleName(), 'getJSON']);

        $route = [Utility::viewize(Utility::normalize(class_basename($m)))];
        if (is_array($moduleName::$json) && isset($moduleName::$json['params'])) {
            foreach ($moduleName::$json['params'] as $param) {
                $route[] = '(?P<' . $param . '>[a-z0-9._-]+)';
            }
        }

        add_action('rest_api_init', function () use ($route, $m): void {
            register_rest_route(
                'sloth/v1/module',
                '/' . implode('/', $route),
                [
                    'methods' => ['GET', 'POST'],
                    'callback' => function (\WP_REST_Request $request) use ($m): void {
                        $m->getJSON($request->get_params());
                    },
                ]
            );
        });
    }

    /**
     * Get all registered modules.
     *
     * @return array<int, string> Array of module class names
     *
     * @since 1.0.0
     */
    public function getModules(): array
    {
        return $this->modules;
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
