<?php
/**
 * Created by PhpStorm.
 * User: Kremer
 * Date: 03.04.17
 * Time: 01:39
 */

namespace Sloth\Controller;

class Controller
{
    protected $layout = 'default';
    protected $request;
    protected $template;
    protected $viewVars = [];

    public function __construct()
    {
    }

    public function afterRender($output)
    {
        return $output;
    }

    public function beforeRender()
    {
    }

    public function invokeAction(&$request)
    {
        $this->request = $request;
        $method = new \ReflectionMethod($this, $request->params['action']);
        $this->beforeRender();
        $this->template = $request->params['action'];
        $method->invokeArgs($this, $request->params['pass']);
        $output = $this->_render();
        $output = $this->afterRender($output);
        echo $output;
    }

    final private function _render()
    {
        return 'Hallo!';
    }

    final private function get($key)
    {
    }

    final private function set($key, $value)
    {
        $this->viewVars[ $key ] = $value;
    }
}
