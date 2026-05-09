<?php

declare(strict_types=1);

namespace Tests\Unit\Options;

use Sloth\Options\Options;

describe('Options', function (): void {

    describe('get()', function (): void {

        it('returns value from get_option()', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn('WordPress');

            expect((new Options())->get('blogname'))->toBe('WordPress');
        });

        it('returns default when option does not exist', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn(false);

            expect((new Options())->get('nonexistent', 'fallback'))->toBe('fallback');
        });

        it('returns null as default when no default given', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn(false);

            expect((new Options())->get('nonexistent'))->toBeNull();
        });

        it('prefers ACF value over get_option() when ACF is available', function (): void {
            \Brain\Monkey\Functions\when('get_field')->justReturn('acf-value');
            \Brain\Monkey\Functions\when('get_option')->justReturn('wp-value');

            expect((new Options())->get('primary_color'))->toBe('acf-value');
        });

        it('falls back to get_option() when ACF returns null', function (): void {
            \Brain\Monkey\Functions\when('get_field')->justReturn(null);
            \Brain\Monkey\Functions\when('get_option')->justReturn('wp-value');

            expect((new Options())->get('blogname'))->toBe('wp-value');
        });

        it('falls back to get_option() when ACF returns false', function (): void {
            \Brain\Monkey\Functions\when('get_field')->justReturn(false);
            \Brain\Monkey\Functions\when('get_option')->justReturn('wp-value');

            expect((new Options())->get('blogname'))->toBe('wp-value');
        });
    });

    describe('set()', function (): void {

        it('calls update_option() and returns true on success', function (): void {
            \Brain\Monkey\Functions\when('update_option')->justReturn(true);

            expect((new Options())->set('my_option', 'value'))->toBeTrue();
        });

        it('returns false on failure', function (): void {
            \Brain\Monkey\Functions\when('update_option')->justReturn(false);

            expect((new Options())->set('my_option', 'value'))->toBeFalse();
        });
    });

    describe('has()', function (): void {

        it('returns true when option exists', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn('some-value');

            expect((new Options())->has('blogname'))->toBeTrue();
        });

        it('returns false when option does not exist', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn(false);

            expect((new Options())->has('nonexistent'))->toBeFalse();
        });
    });

    describe('delete()', function (): void {

        it('calls delete_option() and returns true on success', function (): void {
            \Brain\Monkey\Functions\when('delete_option')->justReturn(true);

            expect((new Options())->delete('my_option'))->toBeTrue();
        });

        it('returns false on failure', function (): void {
            \Brain\Monkey\Functions\when('delete_option')->justReturn(false);

            expect((new Options())->delete('my_option'))->toBeFalse();
        });
    });

    describe('magic access', function (): void {

        it('supports property access via __get()', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn('WordPress');

            expect((new Options())->blogname)->toBe('WordPress');
        });

        it('supports isset() via __isset()', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn('WordPress');

            expect(isset((new Options())->blogname))->toBeTrue();
        });

        it('isset() returns false when option does not exist', function (): void {
            \Brain\Monkey\Functions\when('get_option')->justReturn(false);

            expect(isset((new Options())->nonexistent))->toBeFalse();
        });
    });
});
