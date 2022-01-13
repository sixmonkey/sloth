<?php

namespace Sloth\Module;

use Cake\Utility\Hash;
use Sloth\Facades\View as View;
use Sloth\Utility\Utility;

class Module
{
    public static $ajax_url;
    public static $json = false;
    public static $layotter = false;
    protected $doing_ajax = false;
    protected $render = true;
    protected $template;
    protected $viewPrefix = 'module';
    protected $wrapInRow = false;
    private $view;
    private $viewVars = [];

    final public function __construct($options = [])
    {
        if (isset($options['wrapInRow'])) {
            $this->wrapInRow = $options['wrapInRow'];
        }
    }

    public function get_layotter_attributes()
    {
        $class = get_class($this);

        return $class::$layotter;
    }

    final public function getAjaxAction()
    {
        return 'module_' . Utility::underscore(class_basename($this));
    }

    final public function getAjaxUrl()
    {
        return str_replace(
            home_url(),
            '',
            \admin_url('admin-ajax.php?action=' . $this->getAjaxAction())
        );
    }

    final public function getData($data = [])
    {
        $this->set($data);
        $this->beforeRender();

        return $this->viewVars;
    }

    final public function getJSON($request = null)
    {
        $this->doing_ajax = true;
        $this->beforeRender();
        $this->beforeGetJSON($request);
        header('Content-Type: application/json');
        echo json_encode($this->viewVars, 1);
        die();
    }

    final public function isSet($key)
    {
        return Hash::get($this->viewVars, $key) !== null;
    }

    /**
     * render the view
     */
    public function render()
    {
        if ( ! $this->doing_ajax) {
            $this->set($GLOBALS['sloth::plugin']->getContext(), false);
        }
        $this->set('ajax_url', $this->getAjaxUrl());
        $this->beforeRender();
        $this->makeView();
        $vars   = array_merge($GLOBALS['sloth::plugin']->getContext(), $this->viewVars);
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

    final public function set($key, $value = null, $override = true)
    {
        // @TODO move to Cake Hash behavior
        if (is_array($key)) {
            $override = is_bool($value) ? $value : true;
            foreach ($key as $k => $v) {
                if ($override || ! $this->isSet($k)) {
                    $this->set($k, $v);
                }
            }
        } else {
            if ($override || ! $this->isSet($key)) {
                $this->viewVars[$key] = $this->_prepareValue($value);
            }
        }
    }

    final public function unset($key)
    {
        $this->viewVars = Hash::remove($this->viewVars, $key);
    }

    final protected function _get($k)
    {
        return $this->get($k);
    }

    final protected function _prepareValue($value)
    {
        if (is_a($value, 'WP_Post')) {
            $model_name = $GLOBALS['sloth::plugin']->getPostTypeClass($value->post_type);
            $post       = call_user_func([$model_name, 'find'], $value->ID);
            $value      = $post;
        }

        return $value;
    }

    protected function beforeGetJSON()
    {
    }

    protected function beforeRender()
    {
    }

    final protected function debugViewVars()
    {
        debug($this->viewVars);
    }

    final protected function get($k)
    {
        return Hash::get($this->viewVars, $k);
    }

    final private function getTemplate()
    {
        if (is_null($this->template)) {
            $class          = get_class($this);
            $this->template = \Cake\Utility\Inflector::dasherize(preg_replace(
                '/Module$/',
                '',
                substr(strrchr($class, "\\"), 1)
            ));
        }
        if ( ! strstr($this->template, '.')) {
            $this->template = $this->viewPrefix . '.' . $this->template;
        }
        $this->template = str_replace('.', DS, ucfirst($this->template));
    }

    final private function makeView()
    {
        $this->getTemplate();
        $this->view = View::make($this->template);
    }
}
