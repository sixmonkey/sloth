<?php

declare(strict_types=1);

namespace Sloth\Module;

use Cake\Utility\Hash;
use Illuminate\Support\Str;
use Sloth\Facades\View;
use Sloth\Utility\Utility;

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
    public static $ajax_url = null;

    /**
     * View instance.
     *
     * @since 1.0.0
     * @var mixed
     */
    private $view = null;

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
    protected $template = null;

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
     * @return void
     * @since 1.0.0
     *
     */
    protected function beforeRender()
    {
    }

    /**
     * Called before getting JSON output.
     *
     * @param mixed $payload The payload to process
     *
     * @return void
     * @since 1.0.0
     *
     */
    protected function beforeGetJSON($payload)
    {
    }

    /**
     * Get the template name.
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function getTemplate(): string
    {
        if ($this->template === null) {
            $class = get_class($this);
            $this->template = Str::kebab(preg_replace('/Module$/', '', substr(strrchr($class, '\\'), 1)));
        }

        if (!str_contains((string)$this->template, '.')) {
            $this->template = $this->viewPrefix . '.' . $this->template;
        }
        return $this->template;
    }

    /**
     * Create the view instance.
     *
     * @return void
     * @since 1.0.0
     *
     */
    private function makeView(): void
    {
        $this->view = View::make(str_replace('.', DIRECTORY_SEPARATOR, $this->getTemplate()));
    }

    /**
     * Get Layotter attributes.
     *
     * @return array<string, mixed>|false
     * @since 1.0.0
     *
     */
    final public function getLayotterAttributes(): array|false
    {
        $class = get_class($this);

        return $class::$layotter;
    }

    /**
     * Render the module view.
     *
     * @return string
     * @since 1.0.0
     *
     */
    public function render(): string
    {
        if (!$this->doingAjax) {
            $this->set($GLOBALS['sloth::plugin']->getContext(), false);
        }
        $this->set('ajax_url', $this->getAjaxUrl());
        $this->beforeRender();
        $this->makeView();
        $vars = array_merge($GLOBALS['sloth::plugin']->getContext(), $this->viewVars);
        $output = $this->view->with($vars)->render();

        if ($this->render) {
            if ($this->wrapInRow) {
                $output = View::make('Layotter.row')->with([
                    'content' => $output,
                    'options' => (array)$this->wrapInRow,
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
     * @return void
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
        } else {
            if ($override || !$this->isSet($key)) {
                $this->viewVars[$key] = $this->prepareValue($value);
            }
        }
    }

    /**
     * Get a view variable.
     *
     * @param string $k Variable name
     *
     * @return mixed
     * @since 1.0.0
     *
     */
    final protected function get(string $k): mixed
    {
        return Hash::get($this->viewVars, $k);
    }

    /**
     * Check if a view variable is set.
     *
     * @param string $key Variable name
     *
     * @return bool
     * @since 1.0.0
     *
     */
    final public function isSet(string $key): bool
    {
        return Hash::get($this->viewVars, $key) !== null;
    }

    /**
     * Unset a view variable.
     *
     * @param string $key Variable name
     *
     * @return void
     * @since 1.0.0
     *
     */
    final public function unset(string $key): void
    {
        $this->viewVars = Hash::remove($this->viewVars, $key);
    }

    /**
     * Get a view variable (alias for get).
     *
     * @param string $k Variable name
     *
     * @return mixed
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
     * @return void
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
     * @return string
     * @since 1.0.0
     *
     */
    final public function getAjaxUrl(): string
    {
        return (string)str_replace(
            \home_url(),
            '',
            \admin_url('admin-ajax.php?action=' . $this->getAjaxAction())
        );
    }

    /**
     * Get the AJAX action name.
     *
     * @return string
     * @since 1.0.0
     *
     */
    final public function getAjaxAction(): string
    {
        return 'module_' . Utility::underscore(class_basename($this));
    }

    /**
     * Prepare a value for output.
     *
     * @param mixed $value The value to prepare
     *
     * @return mixed
     * @since 1.0.0
     *
     */
    final protected function prepareValue(mixed $value): mixed
    {
        if (is_a($value, 'WP_Post')) {
            $modelName = $GLOBALS['sloth::plugin']->getPostTypeClass($value->post_type);
            $post = call_user_func([$modelName, 'find'], $value->ID);
            $value = $post;
        }

        return $value;
    }

    /**
     * Debug view variables.
     *
     * @return void
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
     * @return void
     * @since 1.0.0
     *
     */
    public function setTemplate($template): void
    {
        $this->template = $template;
    }
}
