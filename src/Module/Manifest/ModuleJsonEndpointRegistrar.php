<?php

declare(strict_types=1);

namespace Sloth\Module\Manifest;

use Sloth\Module\Module;
use Sloth\Utility\Utility;

/**
 * Registers JSON/AJAX endpoints for modules that have $json enabled.
 *
 * Reads the sloth.modules container binding populated by ModuleManifestBuilder
 * and registers WordPress AJAX handlers and REST routes on the rest_api_init hook.
 *
 * @since 1.0.0
 * @see \Sloth\Module\Manifest\ModuleManifestBuilder
 */
class ModuleJsonEndpointRegistrar
{
    /**
     * Register JSON/AJAX endpoints for modules that have $json enabled.
     *
     * Called on the 'rest_api_init' hook via ModuleServiceProvider.
     *
     * @since 1.0.0
     */
    public function registerJsonEndpoints(): void
    {
        $modules = app()->bound('sloth.modules') ? app('sloth.modules') : [];

        collect($modules)
            ->filter(fn($moduleClass) => (bool) $moduleClass::$json)
            ->each(function ($moduleClass) {
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
