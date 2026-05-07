<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use Illuminate\Http\JsonResponse;
use Sloth\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tests for Sloth\Http\Response.
 *
 * @since 1.0.0
 */
describe('Response', function (): void {
    describe('make()', function (): void {
        it('returns a Response instance', function (): void {
            expect(Response::make())->toBeInstanceOf(Response::class);
        });

        it('sets content', function (): void {
            $response = Response::make('hello');
            expect($response->getContent())->toBe('hello');
        });

        it('sets status code', function (): void {
            $response = Response::make('', 201);
            expect($response->getStatusCode())->toBe(201);
        });

        it('sets headers', function (): void {
            $response = Response::make('', 200, ['Content-Type' => 'text/css']);
            expect($response->headers->get('Content-Type'))->toBe('text/css');
        });

        it('defaults to 200', function (): void {
            expect(Response::make()->getStatusCode())->toBe(200);
        });

        it('supports fluent header()', function (): void {
            $response = Response::make('body')->header('X-Foo', 'bar');
            expect($response->headers->get('X-Foo'))->toBe('bar');
        });
    });

    describe('json()', function (): void {
        it('returns a JsonResponse', function (): void {
            expect(Response::json([]))->toBeInstanceOf(JsonResponse::class);
        });

        it('encodes data as JSON', function (): void {
            $response = Response::json(['key' => 'value']);
            $decoded = json_decode($response->getContent(), true);
            expect($decoded['key'])->toBe('value');
        });

        it('sets status code', function (): void {
            expect(Response::json([], 422)->getStatusCode())->toBe(422);
        });

        it('defaults to 200', function (): void {
            expect(Response::json()->getStatusCode())->toBe(200);
        });
    });

    describe('noContent()', function (): void {
        it('returns 204 by default', function (): void {
            expect(Response::noContent()->getStatusCode())->toBe(204);
        });

        it('returns empty content', function (): void {
            expect(Response::noContent()->getContent())->toBe('');
        });

        it('accepts custom status code', function (): void {
            expect(Response::noContent(205)->getStatusCode())->toBe(205);
        });
    });

    describe('file()', function (): void {
        it('returns a BinaryFileResponse', function (): void {
            $file = tempnam(sys_get_temp_dir(), 'sloth');
            file_put_contents($file, 'content');

            expect(Response::file($file))->toBeInstanceOf(BinaryFileResponse::class);

            unlink($file);
        });
    });

    describe('download()', function (): void {
        it('returns a BinaryFileResponse', function (): void {
            $file = tempnam(sys_get_temp_dir(), 'sloth');
            file_put_contents($file, 'content');

            expect(Response::download($file))->toBeInstanceOf(BinaryFileResponse::class);

            unlink($file);
        });

        it('sets attachment disposition', function (): void {
            $file = tempnam(sys_get_temp_dir(), 'sloth');
            file_put_contents($file, 'content');

            $response = Response::download($file, 'myfile.txt');
            expect($response->headers->get('Content-Disposition'))
                ->toContain('attachment');

            unlink($file);
        });
    });

    describe('redirect()', function (): void {
        it('is a never-returning method', function (): void {
            $reflection = new \ReflectionMethod(Response::class, 'redirect');
            expect($reflection->getReturnType()->getName())->toBe('never');
        });
    });
});
