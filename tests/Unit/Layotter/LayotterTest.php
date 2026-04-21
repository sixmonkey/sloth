<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Layotter;

use Sloth\Layotter\Layotter;

/**
 * Unit tests for the Layotter class.
 */
describe('Layotter', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $layotter = new Layotter();
            expect($layotter)->toBeInstanceOf(Layotter::class);
        });
    });

    describe('Static properties', function (): void {
        it('has disabledPostTypes property', function (): void {
            expect(property_exists(Layotter::class, 'disabledPostTypes'))->toBeTrue();
            expect(Layotter::$disabledPostTypes)->toBeArray();
        });

        it('has enabledPostTypes property', function (): void {
            expect(property_exists(Layotter::class, 'enabledPostTypes'))->toBeTrue();
            expect(Layotter::$enabledPostTypes)->toBeArray();
        });

        it('has layoutsForPostType property', function (): void {
            expect(property_exists(Layotter::class, 'layoutsForPostType'))->toBeTrue();
            expect(Layotter::$layoutsForPostType)->toBeArray();
        });

        it('has layoutsForTemplate property', function (): void {
            expect(property_exists(Layotter::class, 'layoutsForTemplate'))->toBeTrue();
            expect(Layotter::$layoutsForTemplate)->toBeArray();
        });
    });

    describe('enable_for_post_type()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'enable_for_post_type'))->toBeTrue();
        });
    });

    describe('disable_for_post_type()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'disable_for_post_type'))->toBeTrue();
        });
    });

    describe('enableForPostType()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'enableForPostType'))->toBeTrue();
        });
    });

    describe('disableForPostType()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'disableForPostType'))->toBeTrue();
        });
    });

    describe('setLayoutsForPostType()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'setLayoutsForPostType'))->toBeTrue();
        });
    });

    describe('setLayoutsForTemplate()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'setLayoutsForTemplate'))->toBeTrue();
        });
    });

    describe('customColumnClasses()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'customColumnClasses'))->toBeTrue();
        });
    });

    describe('renderLayotterStyles()', function (): void {
        it('method exists', function (): void {
            expect(method_exists(Layotter::class, 'renderLayotterStyles'))->toBeTrue();
        });
    });
});
