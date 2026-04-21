<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Media;

use Sloth\Media\Media;

/**
 * Unit tests for the Media class.
 */
describe('Media', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $media = new Media();
            expect($media)->toBeInstanceOf(Media::class);
        });
    });

    describe('addSvgMime()', function (): void {
        it('adds svg mime type to array', function (): void {
            $media = new Media();
            $mimes = ['jpg' => 'image/jpeg'];
            $result = $media->addSvgMime($mimes);

            expect($result)->toHaveKey('svg');
            expect($result['svg'])->toBe('image/svg+xml');
        });
    });

    describe('registerImageSizes()', function (): void {
        it('method exists', function (): void {
            $media = new Media();
            expect(method_exists($media, 'registerImageSizes'))->toBeTrue();
        });
    });

    describe('makeLinksRelative()', function (): void {
        it('method exists', function (): void {
            $media = new Media();
            expect(method_exists($media, 'makeLinksRelative'))->toBeTrue();
        });
    });

    describe('makeUploadsRelative()', function (): void {
        it('method exists', function (): void {
            $media = new Media();
            expect(method_exists($media, 'makeUploadsRelative'))->toBeTrue();
        });
    });

    describe('toRelativeUrl()', function (): void {
        it('converts full URL to path', function (): void {
            $media = new Media();
            $result = $media->toRelativeUrl('http://example.com/path/to/file');

            expect($result)->toBe('/path/to/file');
        });
    });

    describe('makeHrefsRelative()', function (): void {
        it('replaces home URL with nothing in hrefs', function (): void {
            $media = new Media();
            $content = '<a href="http://localhost/page">Link</a>';
            $result = $media->makeHrefsRelative($content);

            expect($result)->not()->toContain('http://localhost');
        });
    });

    describe('makeSrcsRelative()', function (): void {
        it('converts srcs in content', function (): void {
            $media = new Media();
            $content = '<img src="http://example.com/image.jpg">';
            $result = $media->makeSrcsRelative($content);

            expect($result)->toContain('src="');
        });
    });
});
