<?php

declare(strict_types=1);

namespace Sloth\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sloth HTTP Response.
 *
 * Extends Illuminate's Response with static factory methods so theme
 * code always imports Sloth's own class. If we ever need to extend or
 * override behaviour, it's not a breaking change.
 *
 * ## Usage in routes/web.php
 *
 * ```php
 * use Sloth\Http\Response;
 *
 * Route::get('/css/products', function () {
 *     return Response::make(view('styles.index'), 200)
 *         ->header('Content-Type', 'text/css');
 * });
 *
 * Route::get('/api/data', function () {
 *     return Response::json(['key' => 'value']);
 * });
 * ```
 *
 * @since 1.0.0
 */
class Response extends IlluminateResponse
{
    /**
     * Create a new HTTP response.
     *
     * @param mixed $content The response content.
     * @param int $status HTTP status code.
     * @param array<string, mixed> $headers Additional response headers.
     * @return static
     * @since 1.0.0
     */
    public static function make(mixed $content = '', int $status = 200, array $headers = []): static
    {
        /** @phpstan-ignore new.static */
        return new static($content, $status, $headers);
    }

    /**
     * Create a new JSON response.
     *
     * @param mixed $data Data to encode as JSON.
     * @param int $status HTTP status code.
     * @param array<string, mixed> $headers Additional response headers.
     * @param int $options JSON encoding options.
     * @return JsonResponse
     * @since 1.0.0
     */
    public static function json(
        mixed $data = [],
        int $status = 200,
        array $headers = [],
        int $options = 0,
    ): JsonResponse {
        return new JsonResponse($data, $status, $headers, $options);
    }

    /**
     * Create a new empty response (204 No Content).
     *
     * @param int $status HTTP status code (default 204).
     * @param array<string, mixed> $headers Additional response headers.
     * @return static
     * @since 1.0.0
     */
    public static function noContent(int $status = 204, array $headers = []): static
    {
        /** @phpstan-ignore new.static */
        return new static('', $status, $headers);
    }

    /**
     * Create a file download response.
     *
     * @param \SplFileInfo|string $file Path or file info of the file to download.
     * @param string|null $name Download filename shown to the user.
     * @param array<string, mixed> $headers Additional response headers.
     * @param string $disposition Content-Disposition (attachment or inline).
     * @return BinaryFileResponse
     * @since 1.0.0
     */
    public static function download(
        \SplFileInfo|string $file,
        ?string $name = null,
        array $headers = [],
        string $disposition = 'attachment',
    ): BinaryFileResponse {
        return new BinaryFileResponse($file, 200, $headers, true, $disposition);
    }

    /**
     * Create an inline file response (e.g. display PDF in browser).
     *
     * @param \SplFileInfo|string $file Path or file info of the file to display.
     * @param array<string, mixed> $headers Additional response headers.
     * @return BinaryFileResponse
     * @since 1.0.0
     */
    public static function file(
        \SplFileInfo|string $file,
        array $headers = [],
    ): BinaryFileResponse {
        return new BinaryFileResponse($file, 200, $headers);
    }

    /**
     * Redirect to a URL and terminate the request.
     *
     * Uses wp_redirect() when WordPress is available, falls back to a
     * plain Location header otherwise (e.g. in CLI or standalone context).
     *
     * @param string $url The URL to redirect to.
     * @param int $status HTTP status code (default 302).
     * @param array<string, mixed> $headers Additional response headers.
     * @return never
     * @since 1.0.0
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): never
    {
        foreach ($headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if (function_exists('wp_redirect')) {
            wp_redirect($url, $status);
        } else {
            header("Location: {$url}", true, $status);
        }

        exit;
    }
}
