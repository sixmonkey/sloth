<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Routing;

use Sloth\Routing\RelativeUrlHandler;

describe('RelativeUrlHandler', function (): void {

    describe('toRelativeUrl()', function (): void {
        it('strips scheme and host from a full URL', function (): void {
            $app = makeTestApp();
            $handler = new RelativeUrlHandler($app);

            expect($handler->toRelativeUrl('https://example.com/my/path'))->toBe('/my/path');
        });

        it('strips query string host from a URL with query params', function (): void {
            $app = makeTestApp();
            $handler = new RelativeUrlHandler($app);

            expect($handler->toRelativeUrl('https://example.com/page?foo=bar'))->toBe('/page');
        });

        it('returns just a slash for root URL', function (): void {
            $app = makeTestApp();
            $handler = new RelativeUrlHandler($app);

            expect($handler->toRelativeUrl('https://example.com/'))->toBe('/');
        });

        it('handles URLs without trailing slash', function (): void {
            $app = makeTestApp();
            $handler = new RelativeUrlHandler($app);

            expect($handler->toRelativeUrl('https://example.com'))->toBe('');
        });
    });

    describe('makeHrefsRelative()', function (): void {
        it('converts absolute hrefs to relative', function (): void {
            $app = makeTestApp();
            $app->instance('uri.home', 'https://example.com');
            $handler = new RelativeUrlHandler($app);

            $content = '<a href="https://example.com/about">About</a>';
            $result = $handler->makeHrefsRelative($content);

            expect($result)->toBe('<a href="/about">About</a>');
        });

        it('does not touch external hrefs', function (): void {
            $app = makeTestApp();
            $app->instance('uri.home', 'https://example.com');
            $handler = new RelativeUrlHandler($app);

            $content = '<a href="https://other.com/page">External</a>';
            $result = $handler->makeHrefsRelative($content);

            expect($result)->toBe('<a href="https://other.com/page">External</a>');
        });
    });

    describe('makeSrcsRelative()', function (): void {
        it('converts absolute src attributes to relative', function (): void {
            $app = makeTestApp();
            $app->instance('uri.home', 'https://example.com');
            $handler = new RelativeUrlHandler($app);

            $content = '<img src="https://example.com/wp-content/uploads/image.jpg">';
            $result = $handler->makeSrcsRelative($content);

            expect($result)->toBe('<img src="/wp-content/uploads/image.jpg">');
        });

        it('does not touch external src attributes', function (): void {
            $app = makeTestApp();
            $app->instance('uri.home', 'https://example.com');
            $handler = new RelativeUrlHandler($app);

            $content = '<img src="https://cdn.other.com/image.jpg">';
            $result = $handler->makeSrcsRelative($content);

            expect($result)->toBe('<img src="https://cdn.other.com/image.jpg">');
        });
    });
});
