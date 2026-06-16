<?php

declare(strict_types=1);
namespace Sloth\Module\Registrar;

use function add_action;
use function register_rest_route;
use Sloth\Module\Manifest\ModuleManifestBuilder;
use Sloth\Utility\Utility;
use WP_REST_Request;

/**
 * Registers JSON/AJAX endpoints for modules from manifest entries.
 *
 * Reads the pre-computed entry data from ModuleManifestBuilder and registers
 * WordPress AJAX handlers and REST routes for modules that have `$json` enabled.
 *
 * ## Registration flow
 *
 * 1. ModuleManifestBuilder discovers Module subclasses on the `init` hook.
 * 2. Build-time: class names and JSON config are extracted and cached.
 * 3. ModuleRegistrar reads the cached entries on `rest_api_init` and:
 *    - Registers wp_ajax_nopriv_ and wp_ajax_ handlers
 *    - Registers a REST route under sloth/v1/module/
 *
 * ## Entry data structure
 *
 * Each entry contains:
 * - **className**: the short class name (used for route naming)
 * - **json**: false, true, or an array with optional 'params' key
 * - **jsonParams** (optional): list of URL parameter names for the REST route
 *
 * ## Endpoint format
 *
 * AJAX actions: `wp_ajax_nopriv_{action}` and `wp_ajax_{action}`
 * REST route: `GET/POST /sloth/v1/module/{module-name}[/{param}...]`
 *
 * The `{action}` is derived from the module class name via
 * Utility::normalize() + Utility::viewize().
 *
 * @since 1.0.0
 * @see ModuleManifestBuilder For entry data computation
 * @see \Sloth\Module\ModuleServiceProvider           For hook registration
 */
class ModuleRegistrar
{
    /**
     * Creates a new ModuleRegistrar instance.
     *
     * @param ModuleManifestBuilder $builder the manifest builder that provides
     *                                       the pre-computed entry data
     *
     * @since 1.0.0
     */
    public function __construct(
        private readonly ModuleManifestBuilder $builder,
    ) {
    }

    /**
     * Register JSON/AJAX endpoints for modules that have $json enabled.
     *
     * Iterates over the manifest entries and for each module with a truthy
     * `$json` property:
     *
     * 1. Registers both authenticated and unauthenticated AJAX handlers
     *    that call the module's getJSON() method.
     * 2. Registers a REST route under `sloth/v1/module/{module-name}` with
     *    optional URL parameters from $json['params'].
     *
     * This method is called on the WordPress `rest_api_init` hook via
     * ModuleServiceProvider.
     *
     * @since 1.0.0
     */
    public function registerJsonEndpoints(): void
    {
        foreach ($this->builder->getEntries() as $moduleClass => $entry) {
            if (!(bool) $entry['json']) {
                continue;
            }

            $m = new $moduleClass();

            add_action('wp_ajax_nopriv_' . $m->getAjaxAction(), [$m, 'getJSON']);
            add_action('wp_ajax_' . $m->getAjaxAction(), [$m, 'getJSON']);

            $route = [Utility::viewize(Utility::normalize(class_basename($m)))];

            if (isset($entry['jsonParams'])) {
                foreach ($entry['jsonParams'] as $param) {
                    $route[] = '(?P<' . $param . '>[a-z0-9._-]+)';
                }
            }

            register_rest_route(
                'sloth/v1/module',
                '/' . implode('/', $route),
                [
                    'methods'  => ['GET', 'POST'],
                    'callback' => fn (WP_REST_Request $request) => $m->getJSON($request->get_params()),
                    'permission_callback' => '__return_true',
                ],
            );
        }
    }
}
