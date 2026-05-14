<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Media;

use Sloth\Media\Media;

describe('Media', function (): void {

    describe('addSvgMime()', function (): void {
        it('adds svg mime type to the mimes array', function (): void {
            $app = makeTestApp();
            $media = new Media($app);

            $result = $media->addSvgMime(['jpg' => 'image/jpeg']);

            expect($result)->toHaveKey('svg');
            expect($result['svg'])->toBe('image/svg+xml');
        });

        it('preserves existing mime types', function (): void {
            $app = makeTestApp();
            $media = new Media($app);

            $result = $media->addSvgMime(['jpg' => 'image/jpeg', 'png' => 'image/png']);

            expect($result)->toHaveKey('jpg');
            expect($result)->toHaveKey('png');
            expect($result)->toHaveKey('svg');
        });
    });

    describe('registerImageSizes()', function (): void {
        it('does nothing when image_sizes config is empty', function (): void {
            $app = makeTestApp();
            $app['config']->set('theme.image_sizes', []);
            $media = new Media($app);

            // Should not throw — no add_image_size() calls
            expect(fn () => $media->registerImageSizes())->not()->toThrow(\Throwable::class);
        });

        it('does nothing when image_sizes config is not set', function (): void {
            $app = makeTestApp();
            $media = new Media($app);

            expect(fn () => $media->registerImageSizes())->not()->toThrow(\Throwable::class);
        });
    });
});
