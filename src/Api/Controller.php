<?php

declare(strict_types=1);

namespace Sloth\Api;

use Sloth\Utility\Utility;
use stdClass;

/**
 * Base API controller class for handling REST API requests.
 *
 * @since 1.0.0
 */
class Controller
{
    /**
     * The current request object.
     *
     * @since 1.0.0
     * @var mixed
     */
    protected $request;

    /**
     * The response object.
     *
     * @since 1.0.0
     * @var object
     */
    public $response;

    /**
     * Controller constructor.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->response = new stdClass();
        $this->response->status = 200;
        $this->response->headers = [];
    }

    /**
     * Set HTTP response status code.
     *
     * @param int $code The HTTP status code
     *
     * @since 1.0.0
     */
    public function setStatusCode(int $code): void
    {
        $this->response->status = $code;
    }

    /**
     * Return the index of the resource.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function index(): array
    {
        return [];
    }

    /**
     * Set the request object.
     *
     * @since 1.0.0
     *
     * @param \WP_REST_Request $request The request object
     */
    public function setRequest($request): void
    {
        $this->request = $request;
    }

    /**
     * Build a URL for the API endpoint.
     *
     * @since 1.0.0
     *
     * @param string     $path   The path to the endpoint
     * @param array<string, mixed> $params Optional query parameters
     *
     * @return string The constructed URL
     */
    public function getUrl(string $path, array $params = []): string
    {
        parse_str((string) parse_url($path, PHP_URL_QUERY), $getArray);

        $params = array_merge($getArray, $params);

        $path = '/sloth/v1/' . Utility::viewize(new \ReflectionClass($this)->getShortName()) . '/' . $path;
        if ($params !== []) {
            $path .= '?' . http_build_query($params);
        }

        return (string) get_rest_url(null, $path);
    }
}
