<?php

declare(strict_types=1);

namespace Sloth\Module\Manifest;

use Sloth\Module\Module;
use Sloth\Support\Manifest\AbstractManifestBuilder;
use Sloth\Support\Manifest\ClassMapFinder;
use Sloth\Support\Manifest\FinderInterface;
use Sloth\Utility\Utility;

/**
 * Builds a manifest for module discovery and Layotter registration.
 *
 * Scans app/Module/ and theme/Module/ for Module subclasses and writes a
 * manifest that includes all module files on every request.
 *
 * Layotter element classes are generated directly in the manifest — no
 * eval() at runtime. JSON/AJAX endpoints are registered via ModelServiceProvider
 * on the 'rest_api_init' hook.
 *
 * @since 1.0.0
 * @see \Sloth\Support\Manifest\AbstractManifestBuilder
 */
class ModuleManifestBuilder extends AbstractManifestBuilder
{
    protected function finder(): FinderInterface
    {
        return new ClassMapFinder(Module::class);
    }

    protected function directory(): string
    {
        return 'Module';
    }

    protected function manifestName(): string
    {
        return 'modules.manifest.php';
    }

    protected function extraLines(string $identifier, string $file): array
    {
        /** @var class-string<Module> $moduleClass */
        $moduleClass = $identifier;

        if (!is_array($moduleClass::$layotter) || !class_exists('\\Layotter')) {
            return [];
        }

        // Generate Layotter element class definition directly in the manifest —
        // no eval() at runtime, Opcache handles this like any other class definition.
        $className      = substr(strrchr($moduleClass, '\\'), 1);
        $elementSlug    = strtolower($className);

        return [
            'class ' . $className . ' extends \\Sloth\\Module\\LayotterElement { static $module = ' . var_export($moduleClass, true) . '; }',
            '\\Layotter::register_element(' . var_export($elementSlug, true) . ', ' . var_export($className, true) . ');',
        ];
    }

    protected function bindings(array $map): array
    {
        return [
            'sloth.modules' => array_keys($map),
        ];
    }

    /**
     * Register JSON/AJAX endpoints for modules that have $json enabled.
     *
     * Called on the 'rest_api_init' hook via ModuleServiceProvider.
     *
     * @since 1.0.0
     */
    public function registerJsonEndpoints(): void
    {
        collect(app()->bound('sloth.modules') ? app('sloth.modules') : [])
            ->filter(fn($moduleClass) => (bool) $moduleClass::$json)
            ->each(function ($moduleClass) {
                /** @var class-string<Module> $moduleClass */
                $m = new $moduleClass();

                \add_action('wp_ajax_nopriv_' . $m->getAjaxAction(), [new $moduleClass(), 'getJSON']);
                \add_action('wp_ajax_' . $m->getAjaxAction(), [new $moduleClass(), 'getJSON']);

                $route = [Utility::viewize(Utility::normalize(class_basename($m)))];

                if (is_array($moduleClass::$json) && isset($moduleClass::$json['params'])) {
                    collect($moduleClass::$json['params'])
                        ->each(fn($param) => $route[] = '(?P<' . $param . '>[a-z0-9._-]+)');
                }

                \register_rest_route(
                    'sloth/v1/module',
                    '/' . implode('/', $route),
                    [
                        'methods'  => ['GET', 'POST'],
                        'callback' => fn(\WP_REST_Request $request) => $m->getJSON($request->get_params()),
                    ]
                );
            });
    }
}
