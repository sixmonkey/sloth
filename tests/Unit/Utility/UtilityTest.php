<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Utility;

use Sloth\Utility\Utility;

describe('Utility', function (): void {
    describe('normalize()', function (): void {
        it('removes namespace prefix from fully qualified class names', function (): void {
            expect(Utility::normalize('App\Controller\PageController'))->toBe('PageController');
            expect(Utility::normalize('Some\Long\Namespace\ClassName'))->toBe('ClassName');
            expect(Utility::normalize('\Full\Qualified\Name'))->toBe('Name');
        });

        it('removes trailing Module suffix', function (): void {
            expect(Utility::normalize('HeaderModule'))->toBe('Header');
            expect(Utility::normalize('HeroSectionModule'))->toBe('HeroSection');
        });

        it('converts spaces to hyphens', function (): void {
            expect(Utility::normalize('My Class Name'))->toBe('My-Class-Name');
        });

        it('handles namespaced classes with Module suffix', function (): void {
            expect(Utility::normalize('Theme\Module\HeroModule'))->toBe('Hero');
        });
    });

    describe('viewize()', function (): void {
        it('converts class names to kebab-case', function (): void {
            expect(Utility::viewize('PageController'))->toBe('page-controller');
            expect(Utility::viewize('HomePage'))->toBe('home-page');
            expect(Utility::viewize('MyCustomClass'))->toBe('my-custom-class');
        });

        it('handles fully qualified class names', function (): void {
            expect(Utility::viewize('App\Controller\PageController'))->toBe('page-controller');
        });

        it('handles kebab-case input unchanged', function (): void {
            expect(Utility::viewize('hero-section'))->toBe('hero-section');
        });

        it('handles already kebab-case names', function (): void {
            expect(Utility::viewize('page'))->toBe('page');
        });
    });

    describe('modulize()', function (): void {
        it('converts simple names to PascalCase with Module suffix', function (): void {
            expect(Utility::modulize('page'))->toBe('PageModule');
            expect(Utility::modulize('header'))->toBe('HeaderModule');
        });

        it('converts kebab-case to PascalCase', function (): void {
            expect(Utility::modulize('home-page'))->toBe('HomePageModule');
            expect(Utility::modulize('hero-section'))->toBe('HeroSectionModule');
        });

        it('removes Module suffix before adding it', function (): void {
            expect(Utility::modulize('HeaderModule'))->toBe('HeaderModule');
        });

        it('adds Theme namespace when namespaced is true', function (): void {
            expect(Utility::modulize('page', true))->toBe('Theme\Module\PageModule');
            expect(Utility::modulize('hero-section', true))->toBe('Theme\Module\HeroSectionModule');
        });

        it('does not add namespace when namespaced is false', function (): void {
            expect(Utility::modulize('page', false))->toBe('PageModule');
        });
    });

    describe('acfize()', function (): void {
        it('converts to snake_case by default', function (): void {
            expect(Utility::acfize('heroImage'))->toBe('group_module_hero_image');
            expect(Utility::acfize('HeroImage'))->toBe('group_module_hero_image');
            expect(Utility::acfize('featured_posts'))->toBe('group_module_featured_posts');
        });

        it('adds group_module_ prefix by default', function (): void {
            expect(Utility::acfize('hero_image'))->toBe('group_module_hero_image');
        });

        it('returns snake_case without prefix when prefixed is false', function (): void {
            expect(Utility::acfize('my_field', false))->toBe('my_field');
            expect(Utility::acfize('HeroImage', false))->toBe('hero_image');
        });

        it('handles fully qualified class names', function (): void {
            expect(Utility::acfize('App\Namespace\HeroImage'))->toBe('group_module_hero_image');
        });
    });

    describe('float2fraction()', function (): void {
        it('converts common fractions correctly', function (): void {
            expect(Utility::float2fraction(0.5))->toBe('1/2');
            expect(Utility::float2fraction(0.25))->toBe('1/4');
            expect(Utility::float2fraction(0.75))->toBe('3/4');
        });

        it('converts whole numbers', function (): void {
            expect(Utility::float2fraction(1.0))->toBe('1/1');
            expect(Utility::float2fraction(2.0))->toBe('2/1');
        });

        it('converts improper fractions', function (): void {
            expect(Utility::float2fraction(1.5))->toBe('3/2');
            expect(Utility::float2fraction(2.5))->toBe('5/2');
        });

        it('handles near-zero values', function (): void {
            expect(Utility::float2fraction(0.1))->toBe('1/10');
        });

        it('respects custom tolerance', function (): void {
            $result = Utility::float2fraction(1.618, 1e-2);
            expect($result)->toBe('13/8');
        });

        it('uses default tolerance for precise conversion', function (): void {
            $result = Utility::float2fraction(1.618);
            expect($result)->toBe('809/500');
        });
    });
});
