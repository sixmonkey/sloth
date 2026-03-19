<?php

declare(strict_types=1);

namespace Sloth\Controller;

/**
 * Base controller class for handling requests.
 *
 * @since 1.0.0
 */
class Controller {
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
	 * @var string|null
	 */
	protected ?string $template = null;

	/**
	 * The layout to use for rendering.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $layout = 'default';

	/**
	 * The current request object.
	 *
	 * @since 1.0.0
	 * @var mixed
	 */
	protected mixed $request = null;

	/**
	 * Controller constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
	}

	/**
	 * Called before rendering the view.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function beforeRender(): void {
	}

	/**
	 * Called after rendering the view.
	 *
	 * @since 1.0.0
	 *
	 * @param string $output The rendered output
	 *
	 * @return string
	 */
	public function afterRender(string $output): string {
		return $output;
	}

	/**
	 * Invoke the controller action.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $request The request object
	 *
	 * @return void
	 */
	public function invokeAction(mixed &$request): void {
		$this->request = $request;
		$method = new \ReflectionMethod($this, $request->params['action']);
		$this->beforeRender();
		$this->template = $request->params['action'];
		$method->invokeArgs($this, $request->params['pass']);
		$output = $this->_render();
		$output = $this->afterRender($output);
		echo $output;
	}

	/**
	 * Render the view.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function _render(): string {
		return 'Hallo!';
	}

	/**
	 * Set a view variable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The variable name
	 * @param mixed  $value The variable value
	 *
	 * @return void
	 */
	private function set(string $key, $value): void {
		$this->viewVars[$key] = $value;
	}

	/**
	 * Get a view variable.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The variable name
	 *
	 * @return mixed
	 */
	private function get(string $key) {
		return $this->viewVars[$key] ?? null;
	}
}
