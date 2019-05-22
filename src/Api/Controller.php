<?php

namespace Sloth\Api;

class Controller {
    protected $request;
    public $response;

    public function index() {
        return [];
    }

    final public function __construct() {
        $this->response          = new \stdClass();
        $this->response->status  = 200;
        $this->response->headers = [];
    }

    function setRequest( \WP_REST_Request $request ) {
        $this->request = $request;
    }
}
