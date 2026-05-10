<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Media;

use Sloth\Core\Application;
use Sloth\Media\Media;

/**
 * Unit tests for the Media class.
 */
describe('Media', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $media = new Media(makeTestApp());
            expect($media)->toBeInstanceOf(Media::class);
        });
    });

    describe('addSvgMime()', function (): void {
        it('adds svg mime type to array', function (): void {
            $media = new Media(makeTestApp());
            $mimes = ['jpg' => 'image/jpeg'];
            $result = $media->addSvgMime($mimes);

            expect($result)->toHaveKey('svg');
            expect($result['svg'])->toBe('image/svg+xml');
        });
    });

    describe('registerImageSizes()', function (): void {
        it('method exists', function (): void {
            $media = new Media(makeTestApp());
            expect(method_exists($media, 'registerImageSizes'))->toBeTrue();
        });
    });

    describe('makeLinksRelative()', function (): void {
        it('method exists', function (): void {
            $media = new Media(makeTestApp());
            expect(method_exists($media, 'makeLinksRelative'))->toBeTrue();
        });
    });

    describe('makeUploadsRelative()', function (): void {
        it('method exists', function (): void {
            $media = new Media(makeTestApp());
            expect(method_exists($media, 'makeUploadsRelative'))->toBeTrue();
        });
    });

    describe('toRelativeUrl()', function (): void {
        it('converts full URL to path', function (): void {
            $media = new Media(makeTestApp());
            $result = $media->toRelativeUrl('http://example.com/path/to/file');

            expect($result)->toBe('/path/to/file');
        });
    });

    describe('makeHrefsRelative()', function (): void {
        it('replaces home URL with nothing in hrefs', function (): void {
            $media = new Media(makeTestApp());
            $content = '<a href="http://example.com/page">Link</a>';
            $result = $media->makeHrefsRelative($content);

            expect($result)->toBe('<a href="/page">Link</a>');
        });
    });

    describe('makeSrcsRelative()', function (): void {
        it('converts srcs in content', function (): void {
            $media = new Media(makeTestApp());
            $content = '<img src="http://example.com/wp-content/uploads/image.jpg">';
            $result = $media->makeSrcsRelative($content);

            expect($result)->toBe('<img src="/wp-content/uploads/image.jpg">');
        });
    });
});
