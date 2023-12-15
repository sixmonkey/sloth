<?php

namespace Sloth\Module;

use Illuminate\Support\Str;
use Sloth\Facades\View;
use Sloth\Utility\Utility;
use Cake\Utility\Hash;

class Module
{
    public static $layotter = false;
    public static $json = false;
    private $view;
    private $viewVars = [];
    protected $viewPrefix = 'module';
    protected $render = true;
    protected $template;
    public static $ajax_url;
    protected $doing_ajax = false;
    protected $wrapInRow = false;

    /**
     * Module constructor.
     *
     * @param array $options
     */
    public final function __construct(array $options = [])
    {
        if (isset($options['wrapInRow'])) {
            $this->wrapInRow = $options['wrapInRow'];
        }
    }

    /**
     * Called before rendering the view
     * Override this method to add custom logic to your module
     *
     * @return void
     */
    protected function beforeRender()
    {

    }

    /**
     * Called before a module is rendered as JSON
     * Override this method to add custom logic to your module
     *
     * @return void
     */
    protected function beforeGetJSON($payload)
    {

    }

    /**
     * Gets the template for current module
     *
     * @return void
     */
    private function getTemplate()
    {
        if (is_null($this->template)) {
            $class = get_class($this);
            $this->template = Str::kebab(preg_replace('/Module$/',
                '',
                substr(strrchr($class, "\\"), 1)));
        }
        if (!strstr($this->template, '.')) {
            $this->template = $this->viewPrefix . '.' . $this->template;
        }
        $this->template = str_replace('.', DS, ucfirst($this->template));
    }

    /**
     * Makes the view
     *
     * @return void
     */
    private function makeView()
    {
        $this->getTemplate();
        $this->view = View::make($this->template);
    }

    /**
     * Gets the attributes for layotter
     *
     * @return void
     */
    final public function get_layotter_attributes()
    {
        $class = get_class($this);

        return $class::$layotter;
    }

    /**
     * render the view
     *
     * @return string
     */
    public function render(): string
    {
        if (!$this->doing_ajax) {
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
     * Sets the view variable
     *
     * @param string|array $key
     * @param mixed $value
     * @param bool $override
     * @return void
     */
    final public function set($key, $value = null, bool $override = true)
    {
        // @TODO move to Cake Hash behavior
        if (is_array($key)) {
            $override = !is_bool($value) || $value;
            foreach ($key as $k => $v) {
                if ($override || !$this->isSet($k)) {
                    $this->set($k, $v);
                }
            }
        } else {
            if ($override || !$this->isSet($key)) {
                $this->viewVars[$key] = $this->_prepareValue($value);
            }
        }
    }

    /**
     * Gets the view variable
     *
     * @param string $k
     * @return mixed
     */
    final protected function get(string $k)
    {
        return Hash::get($this->viewVars, $k);
    }

    /**
     * Checks if the view variable is set
     *
     * @param string $key
     * @return bool
     */
    final public function isSet(string $key): bool
    {
        return Hash::get($this->viewVars, $key) !== null;
    }

    /**
     * Unsets the view variable
     *
     * @param string $key
     * @return void
     */
    final public function unset(string $key)
    {
        $this->viewVars = Hash::remove($this->viewVars, $key);
    }

    /**
     * Gets the view variable
     *
     * @param string $k
     * @return mixed
     */
    final protected function _get(string $k)
    {
        return $this->get($k);
    }

    /**
     * Get the view variables as JSON
     *
     * @param null $request
     * @return void
     */
    final public function getJSON($request = null)
    {
        $this->doing_ajax = true;
        $this->beforeRender();
        $this->beforeGetJSON($request);
        header('Content-Type: application/json');
        echo json_encode($this->viewVars, 1);
        die();
    }

    /**
     * @param array $data
     * @return array
     */
    final public function getData(array $data = []): array
    {
        $this->set($data);
        $this->beforeRender();
        return $this->viewVars;
    }

    /**
     * Gets the ajax url
     *
     * @return string
     */
    final public function getAjaxUrl(): string
    {
        return str_replace(\home_url(),
            '',
            \admin_url('admin-ajax.php?action=' . $this->getAjaxAction()));
    }

    /**
     * Gets the ajax action
     *
     * @return string
     */
    final public function getAjaxAction(): string
    {
        return 'module_' . Utility::underscore(class_basename($this));
    }

    /**
     * Prepares the value
     *
     * @param mixed $value
     * @return mixed
     */
    final protected function _prepareValue($value)
    {
        if (is_a($value, 'WP_Post')) {
            $model_name = $GLOBALS['sloth::plugin']->getPostTypeClass($value->post_type);
            $post = call_user_func([$model_name, 'find'], $value->ID);
            $value = $post;
        }

        return $value;
    }

    /**
     * Debugs the view variables
     *
     * @return void
     */
    final protected function debugViewVars()
    {
        debug($this->viewVars);
    }
}
