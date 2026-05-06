<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Sloth\Routing\Router;
use Sloth\Routing\Route;

/**
 * Tests for Sloth\Routing\Router + Route.
 *
 * @since 1.0.0
 */
describe('Router', function (): void {

    describe('registration', function (): void {

        beforeEach(function (): void {
            $this->router = new Router();
        });

        it('get() returns a Route for fluent chaining', function (): void {
            $route = $this->router->get('/about', fn() => 'about');
            expect($route)->toBeInstanceOf(Route::class);
        });

        it('post() returns a Route', function (): void {
            expect($this->router->post('/contact', fn() => 'ok'))
                ->toBeInstanceOf(Route::class);
        });

        it('put() returns a Route', function (): void {
            expect($this->router->put('/posts/1', fn() => 'ok'))
                ->toBeInstanceOf(Route::class);
        });

        it('delete() returns a Route', function (): void {
            expect($this->router->delete('/posts/1', fn() => 'ok'))
                ->toBeInstanceOf(Route::class);
        });

        it('name() can be chained on Route', function (): void {
            $this->router->get('/about', fn() => 'about')->name('about');
            expect($this->router->hasName('about'))->toBeTrue();
        });
    });

    describe('matching', function (): void {

        beforeEach(function (): void {
            $this->router = new Router();
        });

        it('matches a registered GET route', function (): void {
            $cb = fn() => 'hello';
            $this->router->get('/about', $cb);

            $params = $this->router->match('/about', 'GET');

            expect($params)->not->toBeNull();
            expect($params['_controller'])->toBe($cb);
        });

        it('returns null for unknown path', function (): void {
            $this->router->get('/about', fn() => 'about');
            expect($this->router->match('/nope', 'GET'))->toBeNull();
        });

        it('returns null for wrong method', function (): void {
            $this->router->get('/about', fn() => 'about');
            expect($this->router->match('/about', 'POST'))->toBeNull();
        });

        it('resolves {param} placeholders', function (): void {
            $this->router->get('/posts/{slug}', fn($slug) => $slug);

            $params = $this->router->match('/posts/hello-world', 'GET');

            expect($params['slug'])->toBe('hello-world');
        });

        it('resolves multiple {param} placeholders', function (): void {
            $this->router->get('/posts/{year}/{slug}', fn($year, $slug) => '');

            $params = $this->router->match('/posts/2026/hello-world', 'GET');

            expect($params['year'])->toBe('2026');
            expect($params['slug'])->toBe('hello-world');
        });
    });

    describe('url generation', function (): void {

        beforeEach(function (): void {
            $this->router = new Router();
        });

        it('generates URL for a named route', function (): void {
            $this->router->get('/about', fn() => 'about')->name('about');
            expect($this->router->url('about'))->toBe('/about');
        });

        it('generates URL with parameters', function (): void {
            $this->router->get('/posts/{slug}', fn() => '')->name('post.show');
            expect($this->router->url('post.show', ['slug' => 'hello-world']))
                ->toBe('/posts/hello-world');
        });

        it('throws InvalidArgumentException for unknown name', function (): void {
            expect(fn() => $this->router->url('nope'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('dispatch', function (): void {

        beforeEach(function (): void {
            $this->router = new Router();
        });

        it('calls the _controller callback with resolved params', function (): void {
            $called = false;
            $this->router->get('/posts/{slug}', function (string $slug) use (&$called): string {
                $called = true;
                return "post: $slug";
            });

            $params = $this->router->match('/posts/hello-world', 'GET');
            $controller = $params['_controller'];
            $result = $controller(...array_filter(
                $params,
                fn($k) => !str_starts_with($k, '_'),
                ARRAY_FILTER_USE_KEY
            ));

            expect($called)->toBeTrue();
            expect($result)->toBe('post: hello-world');
        });
    });
});
