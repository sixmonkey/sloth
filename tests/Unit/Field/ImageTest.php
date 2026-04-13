<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Field;

use Sloth\Field\Image;

describe('Image', function (): void {
    describe('constructor', function (): void {
        it('handles null input', function (): void {
            $image = new Image(null);
            expect($image->url)->toBeNull();
        });

        it('handles false input', function (): void {
            $image = new Image(false);
            expect($image->url)->toBeNull();
        });
    });

    describe('__toString()', function (): void {
        it('returns empty string when URL is null', function (): void {
            $image = new Image(null);
            expect((string) $image)->toBe('');
        });
    });
});
