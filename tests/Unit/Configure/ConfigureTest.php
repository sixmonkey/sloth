<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Configure;

use PHPUnit\Framework\TestCase;
use Sloth\Configure\Configure;

describe('Configure', function (): void {
    beforeEach(function (): void {
        Configure::reset();
    });

    describe('write() and read()', function (): void {
        it('writes and reads simple keys', function (): void {
            Configure::write('test', 'value');
            expect(Configure::read('test'))->toBe('value');
        });

        it('writes and reads with dot notation', function (): void {
            Configure::write('theme.menus', ['primary' => 'Main Menu']);
            expect(Configure::read('theme.menus'))->toBe(['primary' => 'Main Menu']);
        });

        it('reads nested dot notation', function (): void {
            Configure::write('theme.image-sizes.hero.width', 1920);
            expect(Configure::read('theme.image-sizes.hero.width'))->toBe(1920);
        });

        it('returns null for missing keys', function (): void {
            expect(Configure::read('nonexistent'))->toBeNull();
            expect(Configure::read('parent.child'))->toBeNull();
        });

        it('writes multiple values with array', function (): void {
            Configure::write([
                'first' => 'value1',
                'second' => 'value2',
            ]);
            expect(Configure::read('first'))->toBe('value1');
            expect(Configure::read('second'))->toBe('value2');
        });
    });
});