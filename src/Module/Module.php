<?php

declare(strict_types=1);

namespace Sloth\Module;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Sloth\Facades\View;

/**
 * Base module class for creating modular components.
 *
 * @since 1.0.0
 */
class Module
{
    /**
     * Layotter configuration.
     *
     * @since 1.0.0
     * @var array<string, mixed>|false
     */
    public static $layotter = false;

    /**
     * JSON API configuration.
     *
     * @since 1.0.0
     * @var array<string, mixed>|false
     */
    public static $json = false;

    /**
     * Ajax URL for the module.
     *
     * @since 1.0.0
     * @var string|null
     */
    public static $ajax_url;

    /**
     * View instance.
     *
     * @since 1.0.0
     * @var mixed
     */
    private $view;

    /**
     * View variables.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    private array $viewVars = [];

    /**
     * View prefix for template resolution.
     *
     * @since 1.0.0
     * @var string
     */
    protected $viewPrefix = 'Module';

    /**
     * Whether to render the output.
     *
     * @since 1.0.0
     * @var bool
     */
    protected $render = true;

    /**
     * Template name.
     *
     * @since 1.0.0
     * @var string|null
     */
    protected $template;

    /**
     * Whether an AJAX request is being processed.
     *
     * @since 1.0.0
     * @var bool
     */
    protected $doingAjax = false;

    /**
     * Whether to wrap output in a row.
     *
     * @since 1.0.0
     * @var array<string, mixed>|bool
     */
    protected $wrapInRow = false;

    /**
     * Module constructor.
     *
     * @param array<string, mixed> $options Configuration options
     * @since 1.0.0
     *
     */
    public function __construct(array $options = [])
    {
        if (isset($options['wrapInRow'])) {
            $this->wrapInRow = $options['wrapInRow'];
        }
    }

    /**
     * Called before rendering the view.
     *
     * Override this method in subclasses to perform setup
     * logic before the template is rendered.
     *
     * @since 1.0.0
     */
    protected function beforeRender() {}

    /**
     * Called before getting JSON output.
     *
     * Override this method in subclasses to modify the JSON
     * payload before it's returned in AJAX/REST responses.
     *
     * @param mixed $payload The payload to process (usually array or object)
     * @since 1.0.0
     *
     */
    protected function beforeGetJSON(mixed $payload) {}

    /**
     * Get the template name.
     *
     * Derives the template name from the class name by:
     * 1. Getting the short class name
     * 2. Removing 'Module' suffix
     * 3. Converting to kebab-case
     * 4. Adding 'Module.' prefix (e.g., 'HeaderModule' -> 'Module.header')
     *
     * @return string The template name with prefix (e.g., 'Module.hero-section')
     * @since 1.0.0
     *
     */
    public function getTemplate(): string
    {
        if ($this->template === null) {
            $class = static::class;
            $this->template = Str::kebab(preg_replace('/Module$/', '', substr(strrchr($class, '\\'), 1)));
        }

        if (!str_contains((string) $this->template, '.')) {
            $this->template = $this->viewPrefix . '.' . $this->template;
        }

        return $this->template;
    }

    /**
     * Create the view instance.
     *
     * Initializes the View facade with the resolved template name.
     *
     * @since 1.0.0
     */
    private function makeView(): void
    {
        $this->view = View::make(str_replace('.', DIRECTORY_SEPARATOR, $this->getTemplate()));
    }

    /**
     * Get Layotter attributes for this module.
     *
     * Used by the Layotter page builder to configure the element
     * for this module. Returns the static $layotter configuration.
     *
     * @return array<string, mixed>|false The Layotter config or false if disabled
     * @since 1.0.0
     *
     */
    final public function getLayotterAttributes(): array|false
    {
        $class = static::class;

        return $class::$layotter;
    }

    /**
     * Render the module view.
     *
     * @since 1.0.0
     *
     */
    public function render(): string
    {
        if (!$this->doingAjax) {
            $this->set(app('context')->getContext() ?? [], false);
        }

        $this->set('ajax_url', $this->getAjaxUrl());
        $this->beforeRender();
        $this->makeView();
        $vars = array_merge(app('context')->getContext() ?? [], $this->viewVars);
        $output = $this->view->with($vars)->render();

        if ($this->render) {
            if ($this->wrapInRow) {
                $output = View::make('Layotter.row')->with([
                    'content' => $output,
                    'options' => (array) $this->wrapInRow,
                ])->render();
            }

            echo $output;
        }

        return $output;
    }

    /**
     * Set a view variable.
     *
     * @param string|array<string, mixed> $key Variable name or array of variables
     * @param mixed $value Variable value (ignored if $key is array)
     * @param bool $override Whether to override existing values
     *
     * @since 1.0.0
     *
     */
    final public function set(string|array $key, mixed $value = null, bool $override = true): void
    {
        if (is_array($key)) {
            $override = !is_bool($value) || $value;
            foreach ($key as $k => $v) {
                if ($override || !$this->isSet($k)) {
                    $this->set($k, $v);
                }
            }
        } elseif ($override || !$this->isSet($key)) {
            $this->viewVars[$key] = $this->prepareValue($value);
        }
    }

    /**
     * Get a view variable.
     *
     * @param string $k Variable name
     *
     * @since 1.0.0
     *
     */
    final protected function get(string $k): mixed
    {
        return Arr::get($this->viewVars, $k);
    }

    /**
     * Check if a view variable is set.
     *
     * @param string $key Variable name
     *
     * @since 1.0.0
     *
     */
    final public function isSet(string $key): bool
    {
        return Arr::get($this->viewVars, $key) !== null;
    }

    /**
     * Unset a view variable.
     *
     * @param string $key Variable name
     *
     * @since 1.0.0
     *
     */
    final public function unset(string $key): void
    {
        Arr::forget($this->viewVars, $key);
    }

    /**
     * Get a view variable (alias for get).
     *
     * @param string $k Variable name
     *
     * @since 1.0.0
     *
     */
    final protected function _get(string $k): mixed
    {
        return $this->get($k);
    }

    /**
     * Get JSON output.
     *
     * @param mixed $request The request object
     *
     * @throws \JsonException
     * @since 1.0.0
     *
     */
    final public function getJSON(mixed $request = null): void
    {
        $this->doingAjax = true;
        $this->beforeRender();
        $this->beforeGetJSON($request);
        header('Content-Type: application/json');
        echo json_encode($this->viewVars, JSON_THROW_ON_ERROR);
        die();
    }

    /**
     * Get the view data.
     *
     * @param array<string, mixed> $data Additional data to set
     *
     * @return array<string, mixed>
     * @since 1.0.0
     *
     */
    final public function getData(array $data = []): array
    {
        $this->set($data);
        $this->beforeRender();
        return $this->viewVars;
    }

    /**
     * Get the AJAX URL.
     *
     * @since 1.0.0
     *
     */
    final public function getAjaxUrl(): string
    {
        return (string) str_replace(
            \home_url(),
            '',
            \admin_url('admin-ajax.php?action=' . $this->getAjaxAction())
        );
    }

    /**
     * Get the AJAX action name.
     *
     * @since 1.0.0
     *
     */
    final public function getAjaxAction(): string
    {
        return 'module_' . Str::snake(class_basename($this));
    }

    /**
     * Prepare a value for output.
     *
     * @param mixed $value The value to prepare
     *
     * @since 1.0.0
     *
     */
    final protected function prepareValue(mixed $value): mixed
    {
        if (is_a($value, 'WP_Post')) {
            $modelName = (app('sloth.models') ?? [])[$value->post_type] ?? \Sloth\Model\Model::class;
            $post = call_user_func([$modelName, 'find'], $value->ID);
            $value = $post;
        }

        return $value;
    }

    /**
     * Debug view variables.
     *
     * @since 1.0.0
     *
     */
    final protected function debugViewVars(): void
    {
        debug($this->viewVars);
    }

    /**
     * Set the template name.
     *
     * @param string $template Template name
     *
     * @since 1.0.0
     *
     */
    public function setTemplate($template): void
    {
        $this->template = $template;
    }
}
