<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model;

use Sloth\Model\Image;

describe('Image', function (): void {
    describe('urlToRelativePath()', function (): void {
        $base = 'http://example.com/wp-content/uploads';

        it('strips base URL prefix', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads/2024/05/photo.jpg',
                $base,
            );

            expect($result)->toBe('2024/05/photo.jpg');
        });

        it('handles relative path as-is', function () use ($base): void {
            $result = Image::urlToRelativePath('2024/05/photo.jpg', $base);

            expect($result)->toBe('2024/05/photo.jpg');
        });

        it('handles path with leading slash relative', function () use ($base): void {
            $result = Image::urlToRelativePath('/2024/photo.jpg', $base);

            expect($result)->toBe('2024/photo.jpg');
        });

        it('handles different base URL scheme', function (): void {
            $result = Image::urlToRelativePath(
                'https://cdn.example.com/uploads/2024/photo.jpg',
                'https://cdn.example.com/uploads',
            );

            expect($result)->toBe('2024/photo.jpg');
        });

        it('handles URL with subdir in base', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads/subdir/image.png',
                $base,
            );

            expect($result)->toBe('subdir/image.png');
        });

        it('returns full URL when base URL does not match', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'https://external.cdn.com/images/photo.jpg',
                $base,
            );

            expect($result)->toBe('https://external.cdn.com/images/photo.jpg');
        });

        it('handles empty string', function () use ($base): void {
            $result = Image::urlToRelativePath('', $base);

            expect($result)->toBe('');
        });

        it('handles URL that exactly equals base URL', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads',
                $base,
            );

            expect($result)->toBe('');
        });

        it('handles filename directly in uploads root', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads/logo.png',
                $base,
            );

            expect($result)->toBe('logo.png');
        });

        it('handles nested year/month subdirectories', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads/2024/12/31/image.jpeg',
                $base,
            );

            expect($result)->toBe('2024/12/31/image.jpeg');
        });

        it('does not strip matching directory name from middle of path', function () use ($base): void {
            $result = Image::urlToRelativePath(
                'http://example.com/wp-content/uploads/2024/uploads-image.jpg',
                $base,
            );

            expect($result)->toBe('2024/uploads-image.jpg');
        });
    });

    describe('class structure', function (): void {
        it('extends base Model', function (): void {
            expect(new \ReflectionClass(Image::class))
                ->isSubclassOf(\Sloth\Model\Model::class)
                ->toBeTrue();
        });

        it('has findByIdOrUrl method', function (): void {
            expect(method_exists(Image::class, 'findByIdOrUrl'))->toBeTrue();
        });

        it('has findByUrl method', function (): void {
            expect(method_exists(Image::class, 'findByUrl'))->toBeTrue();
        });

        it('has urlToRelativePath method', function (): void {
            expect(method_exists(Image::class, 'urlToRelativePath'))->toBeTrue();
        });
    });
});
