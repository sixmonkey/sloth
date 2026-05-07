<?php

declare(strict_types=1);
namespace Sloth\Http;

use Inpsyde\WpContext;

/**
 * Request Context.
 *
 * Wraps inpsyde/wp-context for WordPress request detection with early
 * REST/AJAX detection via URI prefix (works before WP sets constants).
 *
 * ## Why not use WpContext directly?
 *
 * `WpContext::determine()` is called on `after_setup_theme` (priority 0),
 * which fires BEFORE WordPress sets `REST_REQUEST` (set later during
 * `rest_api_loaded`). So `isRest()` returns false at this point even
 * for actual REST API requests.
 *
 * This class adds early detection via `$_SERVER['REQUEST_URI']` for
 * the REST API prefix, and via `SCRIPT_NAME` for AJAX, which are
 * available from the very first line of execution.
 *
 * ## REST Prefix Resolution
 *
 * The REST API prefix can be changed via the `rest_url_prefix` filter.
 * Since that filter may not be registered yet in early lifecycle,
 * this class resolves the prefix via `WP_REST_PREFIX` env var with
 * fallback to `wp-json` (WordPress default).
 *
 * ## Usage
 *
 * ```php
 * $context = $app->make(RequestContext::class);
 *
 * // WordPress context (delegates to WpContext)
 * $context->isRest();        // true for REST API requests
 * $context->isAjax();        // true for admin-ajax.php
 * $context->isFrontoffice(); // true for frontend requests
 * ```
 *
 * @since 1.0.0
 * @see WpContext
 */
class RequestContext
{
    /**
     * The underlying WordPress context instance.
     *
     * @since 1.0.0
     */
    protected WpContext $wpContext;

    /**
     * REST API prefix for early detection.
     *
     * @since 1.0.0
     */
    protected ?string $restPrefix = null;

    /**
     * Create a new RequestContext instance.
     *
     * @param WpContext|null $wpContext  optional WpContext instance (for testing)
     * @param string|null    $restPrefix optional REST prefix (falls back to WP_REST_PREFIX env var, then 'wp-json')
     *
     * @since 1.0.0
     */
    public function __construct(?WpContext $wpContext = null, ?string $restPrefix = null)
    {
        $this->wpContext = $wpContext ?? WpContext::determine();
        $this->restPrefix = $restPrefix;
    }

    // -------------------------------------------------------------------------
    // WordPress Context
    // -------------------------------------------------------------------------

    /**
     * Check if the current request is a REST API request.
     *
     * Works at any point in the request lifecycle:
     * - Late (after rest_api_loaded): checks REST_REQUEST constant and WpContext
     * - Early (during boot): checks URI prefix and rest_route query var
     *
     * @return bool true if this is a REST API request
     *
     * @since 1.0.0
     */
    public function isRest(): bool
    {
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return true;
        }

        if (!empty($_GET['rest_route'])) {
            return true;
        }

        if ($this->wpContext->isRest()) {
            return true;
        }

        return $this->isRestFromUri();
    }

    /**
     * Check if the current request is an AJAX request.
     *
     * @return bool true if this is an AJAX request
     *
     * @since 1.0.0
     */
    public function isAjax(): bool
    {
        return $this->wpContext->isAjax() || $this->isEarlyAjaxRequest();
    }

    /**
     * Check if the current request is a WordPress admin request.
     *
     * @return bool true if this is a backoffice request
     *
     * @since 1.0.0
     */
    public function isBackoffice(): bool
    {
        return $this->wpContext->isBackoffice();
    }

    /**
     * Check if the current request is a frontend request.
     *
     * Excludes REST and AJAX requests even if WpContext reports
     * frontoffice (which it may do early in the lifecycle).
     *
     * @return bool true if this is a frontoffice request
     *
     * @since 1.0.0
     */
    public function isFrontoffice(): bool
    {
        return $this->wpContext->isFrontoffice()
            && !$this->isRest()
            && !$this->isAjax();
    }

    /**
     * Check if the current request is a cron request.
     *
     * @return bool true if this is a cron request
     *
     * @since 1.0.0
     */
    public function isCron(): bool
    {
        return $this->wpContext->isCron();
    }

    /**
     * Check if the current request is a WP-CLI request.
     *
     * @return bool true if this is a WP-CLI request
     *
     * @since 1.0.0
     */
    public function isCli(): bool
    {
        return $this->wpContext->isWpCli();
    }

    /**
     * Check if the current request is an XML-RPC request.
     *
     * @return bool true if this is an XML-RPC request
     *
     * @since 1.0.0
     */
    public function isXmlRpc(): bool
    {
        return $this->wpContext->isXmlRpc();
    }

    /**
     * Check if WordPress is currently installing.
     *
     * @return bool true if WordPress is installing
     *
     * @since 1.0.0
     */
    public function isInstalling(): bool
    {
        return $this->wpContext->isInstalling();
    }

    /**
     * Check if the current request is a login page.
     *
     * @return bool true if this is a login request
     *
     * @since 1.0.0
     */
    public function isLogin(): bool
    {
        return $this->wpContext->isLogin();
    }

    /**
     * Check if a user is currently logged in.
     *
     * @return bool true if a user is logged in
     *
     * @since 1.0.0
     */
    public function isLoggedin(): bool
    {
        return function_exists('is_user_logged_in') && is_user_logged_in();
    }

    /**
     * Get the underlying WpContext instance.
     *
     * @return WpContext the WpContext instance
     *
     * @since 1.0.0
     */
    public function wpContext(): WpContext
    {
        return $this->wpContext;
    }

    /**
     * Check if the current response is JSON.
     *
     * @param  string|null $output optional output to inspect
     * @return bool        true if this is a JSON response
     *
     * @since 1.0.0
     */
    public function isJsonResponse(?string $output = null): bool
    {
        if ($this->isRest() || $this->isAjax()) {
            return true;
        }

        if ($output !== null) {
            $trimmed = ltrim($output);

            if ($trimmed !== '' && in_array($trimmed[0], ['{', '['], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the current response is XML.
     *
     * @param  string|null $output optional output to inspect
     * @return bool        true if this is an XML response
     *
     * @since 1.0.0
     */
    public function isXmlResponse(?string $output = null): bool
    {
        if ($this->isXmlRpc()) {
            return true;
        }

        if ($output !== null) {
            $trimmed = ltrim($output);

            if (str_starts_with(strtolower($trimmed), '<?xml')) {
                return true;
            }
        }

        return false;
    }

    // -------------------------------------------------------------------------
    // Early Detection (before WordPress sets constants)
    // -------------------------------------------------------------------------

    /**
     * Detect REST requests early via URI prefix.
     *
     * @return bool true if the request URI starts with the REST prefix
     *
     * @since 1.0.0
     */
    protected function isRestFromUri(): bool
    {
        $prefix = $this->getRestPrefix();
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

        if ($requestPath === false || $requestPath === null) {
            return false;
        }

        return str_starts_with(ltrim($requestPath, '/'), $prefix);
    }

    /**
     * Detect AJAX requests early via script name.
     *
     * @return bool True if the script is admin-ajax.php.
     *
     * @since 1.0.0
     */
    protected function isEarlyAjaxRequest(): bool
    {
        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');

        return $script === 'admin-ajax.php';
    }

    /**
     * Get the REST API prefix.
     *
     * Resolution order:
     * 1. Explicitly passed to constructor
     * 2. WP_REST_PREFIX env var
     * 3. Default 'wp-json'
     *
     * @return string the REST API prefix
     *
     * @since 1.0.0
     */
    protected function getRestPrefix(): string
    {
        if ($this->restPrefix !== null) {
            return $this->restPrefix;
        }

        return $this->restPrefix = env('WP_REST_PREFIX', 'wp-json');
    }
}
