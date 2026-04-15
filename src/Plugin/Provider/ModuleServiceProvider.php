<?php

declare(strict_types=1);

namespace Sloth\Plugin\Provider;

use Sloth\Utility\Utility;

/**
 * Service provider for module discovery and registration.
 *
 * Handles:
 * - Discovery of module classes from get_template_directory()/Module/
 * - Layotter element registration for page builder integration
 * - JSON/AJAX endpoint registration for module data
 *
 * ## Module Discovery
 *
 * Discovers all PHP files in the theme's Module/ directory that
 * end with Module.php (e.g., HeroModule.php, TestimonialModule.php).
 *
 * ## Layotter Integration
 *
 * Modules with a $layotter array property are registered as
 * Layotter page builder elements. The dynamic class is created
 * via eval() and registered with Layotter::register_element().
 *
 * ## JSON Endpoints
 *
 * Modules with $json = true (or an array with 'params') get:
 * - AJAX handlers for wp_ajax and wp_ajax_nopriv
 * - REST API routes under /sloth/v1/module/
 *
 * Example route: GET /sloth/v1/module/testimonial/latest
 *
 * @since 1.0.0
 * @see \Sloth\Module\Module
 * @see \Sloth\Module\LayotterElement
 * @see \Sloth\Plugin\Plugin
 */
class ModuleServiceProvider
{
    /**
     * Registered module class names.
     *
     * @var array<int, string>
     */
    protected array $modules = [];

    /**
     * Register modules from theme Module/ directory.
     *
     * Discovers module classes from get_template_directory()/Module/,
     * registers them with Layotter if configured, and sets up
     * JSON/AJAX endpoints.
     *
     * ## Registration Process
     *
     * For each module class found:
     * 1. Load the class file
     * 2. If $layotter is configured and Layotter exists, register element
     * 3. If $json is configured, register AJAX and REST endpoints
     *
     * @since 1.0.0
     */
    public function register(): void
    {
        foreach (glob(get_template_directory() . DS . 'Module' . DS . '*Module.php') as $file) {
            $moduleName = $this->loadClassFromFile($file);
            add_action('init', function () use ($moduleName): void {
                $this->registerLayotterElement($moduleName);
            });
            $this->registerJsonEndpoints($moduleName);

            $this->modules[] = $moduleName;
        }
    }

    /**
     * Register a module as a Layotter element.
     *
     * Creates a dynamic class extending LayotterElement and registers
     * it with Layotter::register_element(). This allows modules to
     * be used as page builder elements.
     *
     * @param string $moduleName The fully qualified module class name
     * @since 1.0.0
     *
     */
    protected function registerLayotterElement(string $moduleName): void
    {
        if (!is_array($moduleName::$layotter) || !class_exists('\\Layotter')) {
            return;
        }

        $className = substr(strrchr($moduleName, '\\'), 1);
        $moduleClassName = $moduleName;

        eval("class {$className} extends \\Sloth\\Module\\LayotterElement {
\t\t\t\tstatic \$module = '{$moduleClassName}';
\t\t\t}");
        \Layotter::register_element(strtolower(substr(strrchr($moduleName, '\\'), 1)), $className);
    }

    /**
     * Register JSON/AJAX endpoints for a module.
     *
     * Sets up both AJAX handlers (wp_ajax and wp_ajax_nopriv) and
     * REST API routes for module JSON data retrieval.
     *
     * @param string $moduleName The fully qualified module class name
     * @since 1.0.0
     *
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
     * @since 1.0.0
     *
     */
    public function getModules(): array
    {
        return $this->modules;
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
