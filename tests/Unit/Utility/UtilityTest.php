<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Utility;

use Sloth\Utility\Utility;

describe('Utility', function (): void {
    describe('viewize()', function (): void {
        it('converts class names to view-style slugs', function (): void {
            expect(Utility::viewize('PageController'))->toBe('page-controller');
            expect(Utility::viewize('HomePage'))->toBe('home-page');
            expect(Utility::viewize('MyCustomClass'))->toBe('my-custom-class');
        });
    });

    describe('normalize()', function (): void {
        it('normalizes class names by removing namespace prefix', function (): void {
            expect(Utility::normalize('App\Controller\PageController'))->toBe('PageController');
            expect(Utility::normalize('Some\Long\Namespace\ClassName'))->toBe('ClassName');
        });
    });

    describe('modulize()', function (): void {
        it('converts to module-style names', function (): void {
            expect(Utility::modulize('page'))->toBe('PageModule');
            expect(Utility::modulize('home-page'))->toBe('HomePageModule');
        });

        it('adds Theme namespace when namespaced', function (): void {
            expect(Utility::modulize('Page', true))->toBe('Theme\Module\PageModule');
        });
    });

    describe('acfize()', function (): void {
        it('adds group_module_ prefix', function (): void {
            expect(Utility::acfize('hero_image'))->toBe('group_module_hero_image');
        });

        it('returns original without prefix', function (): void {
            expect(Utility::acfize('my_field', false))->toBe('my_field');
        });
    });

    describe('float2fraction()', function (): void {
        it('converts common floats to fractions', function (): void {
            expect(Utility::float2fraction(0.5))->toBe('1/2');
            expect(Utility::float2fraction(0.25))->toBe('1/4');
        });
    });
});