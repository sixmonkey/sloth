<?php

namespace Sloth\Api;

use Sloth\Utility\Utility;

class Controller
{
    public $response;
    protected $request;

    final public function __construct()
    {
        $this->response          = new \stdClass();
        $this->response->status  = 200;
        $this->response->headers = [];
    }

    public function index()
    {
        return [];
    }

    final public function setRequest(\WP_REST_Request $request)
    {
        $this->request = $request;
    }

    final protected function getUrl(string $path, array $params = [])
    {
        parse_str(parse_url($path, PHP_URL_QUERY), $get_array);

        $params = array_merge($get_array, $params);

        $path = '/sloth/v1/' . Utility::viewize(( new \ReflectionClass($this) )->getShortName()) . '/' . $path;
        if (count($params)) {
            $path .= '?' . http_build_query($params);
        }

        return get_rest_url(null, $path);
    }
}
