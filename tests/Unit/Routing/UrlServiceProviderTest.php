<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Routing;

use Sloth\Routing\RelativeUrlHandler;
use Sloth\Routing\UrlServiceProvider;

describe('UrlServiceProvider', function (): void {

    describe('register()', function (): void {
        it('binds RelativeUrlHandler in the container', function (): void {
            $app = makeTestApp();
            $provider = new UrlServiceProvider($app);
            $provider->register();

            expect($app->bound(RelativeUrlHandler::class))->toBeTrue();
            expect($app->make(RelativeUrlHandler::class))->toBeInstanceOf(RelativeUrlHandler::class);
        });
    });

    describe('getFilters()', function (): void {
        it('returns no filters when all relative URL options are disabled', function (): void {
            $app = makeTestApp();
            $provider = new UrlServiceProvider($app);
            $provider->register();

            expect($provider->getFilters())->toBeEmpty();
        });

        it('registers link filters when relative_links is enabled', function (): void {
            $app = makeTestApp();
            $app['config']->set('app.relative_links', true);
            $provider = new UrlServiceProvider($app);
            $provider->register();

            $filters = $provider->getFilters();
            expect($filters)->toHaveKey('post_link');
            expect($filters)->toHaveKey('page_link');
            expect($filters)->toHaveKey('the_content');
        });

        it('registers upload filters when relative_uploads is enabled', function (): void {
            $app = makeTestApp();
            $app['config']->set('app.relative_uploads', true);
            $provider = new UrlServiceProvider($app);
            $provider->register();

            $filters = $provider->getFilters();
            expect($filters)->toHaveKey('wp_get_attachment_url');
            expect($filters)->toHaveKey('attachment_link');
        });

        it('enables both links and uploads when relative_urls is true', function (): void {
            $app = makeTestApp();
            $app['config']->set('app.relative_urls', true);
            $provider = new UrlServiceProvider($app);
            $provider->register();

            $filters = $provider->getFilters();
            expect($filters)->toHaveKey('post_link');
            expect($filters)->toHaveKey('wp_get_attachment_url');
        });
    });
});
