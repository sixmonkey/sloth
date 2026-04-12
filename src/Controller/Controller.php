<?php

declare(strict_types=1);

namespace Sloth\Controller;

/**
 * Base controller class for handling requests.
 *
 * @since 1.0.0
 */
class Controller
{
    /**
     * View variables to be passed to the view.
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    protected array $viewVars = [];

    /**
     * The current template name.
     *
     * @since 1.0.0
     */
    protected ?string $template = null;

    /**
     * The layout to use for rendering.
     *
     * @since 1.0.0
     */
    protected string $layout = 'default';

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
        $this->response = new \stdClass();
        $this->response->status = 200;
        $this->response->headers = [];
    }

    /**
     * Called before rendering the view.
     *
     * Override in subclasses to perform setup before the view renders.
     *
     * @since 1.0.0
     */
    public function beforeRender(): void
    {
    }

    /**
     * Called after rendering the view.
     *
     * Override in subclasses to modify the rendered output before it's sent.
     *
     * @since 1.0.0
     *
     * @param string $output The rendered HTML output
     * @return string The modified output
     */
    public function afterRender(string $output): string
    {
        return $output;
    }

    /**
     * Invoke the controller action.
     *
     * Uses reflection to call the requested action method with the
     * URL parameters. Wraps execution in output buffering to capture
     * the rendered view, then calls afterRender() to modify output.
     *
     * @since 1.0.0
     *
     * @param mixed $request The request object containing params, action, pass
     * @throws \ReflectionException If the action method doesn't exist
     */
    public function invokeAction(mixed &$request): void
    {
        $this->request = $request;
        $method = new \ReflectionMethod($this, $request->params['action']);
        $this->beforeRender();
        $this->template = $request->params['action'];
        ob_start();
        $method->invokeArgs($this, $request->params['pass']);
        $output = ob_get_clean();
        $output = $this->afterRender($output);

        status_header($this->response->status);
        echo $output;
    }

    /**
     * Default index action.
     *
     * Override in subclasses to define the default action when
     * no specific action is requested.
     *
     * @since 1.0.0
     */
    public function index()
    {
    }
}
