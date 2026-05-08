<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use Sloth\Core\Application;
use Sloth\Routing\Router;
use Sloth\Routing\UrlGenerator;

describe('UrlGenerator', function (): void {

    $setup = function (): UrlGenerator {
        $app = makeTestApp();
        $app->addUri('home', 'https://example.com');
        $app->addUri('theme', 'https://example.com/wp-content/themes/my-theme');
        $app->addUri('content', 'https://example.com/wp-content');
        $app->addUri('uploads', 'https://example.com/wp-content/uploads');
        Application::setInstance($app);

        return new UrlGenerator($app, new Router());
    };

    describe('home()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns home URI without path', function (): void {
            expect($this->generator->home())->toBe('https://example.com');
        });

        it('appends path to home URI', function (): void {
            expect($this->generator->home('/about'))->toBe('https://example.com/about');
        });

        it('handles path without leading slash', function (): void {
            expect($this->generator->home('about'))->toBe('https://example.com/about');
        });
    });

    describe('to()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('is an alias for home()', function (): void {
            expect($this->generator->to('/about'))->toBe('https://example.com/about');
        });
    });

    describe('theme()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns theme URI without path', function (): void {
            expect($this->generator->theme())
                ->toBe('https://example.com/wp-content/themes/my-theme');
        });

        it('appends path to theme URI', function (): void {
            expect($this->generator->theme('css/app.css'))
                ->toBe('https://example.com/wp-content/themes/my-theme/css/app.css');
        });
    });

    describe('asset()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('prepends public/ to the path', function (): void {
            expect($this->generator->asset('css/app.css'))
                ->toBe('https://example.com/wp-content/themes/my-theme/public/css/app.css');
        });

        it('handles leading slash in path', function (): void {
            expect($this->generator->asset('/css/app.css'))
                ->toBe('https://example.com/wp-content/themes/my-theme/public/css/app.css');
        });
    });

    describe('content()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns content URI without path', function (): void {
            expect($this->generator->content())->toBe('https://example.com/wp-content');
        });

        it('appends path to content URI', function (): void {
            expect($this->generator->content('plugins/acf'))
                ->toBe('https://example.com/wp-content/plugins/acf');
        });
    });

    describe('uploads()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns uploads URI without path', function (): void {
            expect($this->generator->uploads())->toBe('https://example.com/wp-content/uploads');
        });

        it('appends path to uploads URI', function (): void {
            expect($this->generator->uploads('2026/05/image.jpg'))
                ->toBe('https://example.com/wp-content/uploads/2026/05/image.jpg');
        });
    });

    describe('route()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('generates URL for a named route', function (): void {
            $app = makeTestApp();
            $app->addUri('home', 'https://example.com');
            $router = new Router();
            $router->get('/posts/{slug}', fn($slug) => $slug)->name('post.show');

            $generator = new UrlGenerator($app, $router);

            expect($generator->route('post.show', ['slug' => 'hello-world']))
                ->toBe('https://example.com/posts/hello-world');
        });

        it('throws for unknown route name', function (): void {
            expect(fn() => $this->generator->route('nonexistent'))
                ->toThrow(\InvalidArgumentException::class);
        });
    });

    describe('current()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns the current request path', function (): void {
            $_SERVER['REQUEST_URI'] = '/about?foo=bar';

            expect($this->generator->current())->toBe('/about');
        });

        it('defaults to / when REQUEST_URI is not set', function (): void {
            unset($_SERVER['REQUEST_URI']);

            expect($this->generator->current())->toBe('/');
        });
    });

    describe('full()', function () use ($setup): void {

        beforeEach(function () use ($setup): void {
            $this->generator = $setup();
        });

        it('returns home URI with current path', function (): void {
            $_SERVER['REQUEST_URI'] = '/about';

            expect($this->generator->full())->toBe('https://example.com/about');
        });
    });
});
