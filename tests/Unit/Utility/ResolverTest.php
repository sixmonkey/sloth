<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Utility;

use Sloth\Utility\ClassResolver\ClassResolver;
use Sloth\Model\Resolvers\ModelsResolver;
use Sloth\Model\Resolvers\TaxonomiesResolver;
use Sloth\Api\Resolvers\ApiControllersResolver;
use Sloth\Module\Resolvers\ModulesResolver;

/**
 * Unit tests for the Resolver classes.
 */
describe('Resolver Classes', function (): void {
    describe('ModelsResolver', function (): void {
        it('extends ClassResolver', function (): void {
            expect(is_subclass_of(ModelsResolver::class, ClassResolver::class))->toBeTrue();
        });

        it('has correct subclassOf configured', function (): void {
            $reflection = new \ReflectionClass(ModelsResolver::class);
            $prop = $reflection->getProperty('subclassOf');
            expect($prop->getValue())->toBe(\Sloth\Model\Model::class);
        });

        it('has dir configured', function (): void {
            $reflection = new \ReflectionClass(ModelsResolver::class);
            $prop = $reflection->getProperty('dir');
            expect($prop->getValue())->toBe('Model');
        });

        it('has cacheKey configured', function (): void {
            $reflection = new \ReflectionClass(ModelsResolver::class);
            $prop = $reflection->getProperty('cacheKey');
            expect($prop->getValue())->toBe('sloth.class-resolver.models');
        });
    });

    describe('TaxonomiesResolver', function (): void {
        it('extends ClassResolver', function (): void {
            expect(is_subclass_of(TaxonomiesResolver::class, ClassResolver::class))->toBeTrue();
        });

        it('has correct subclassOf configured', function (): void {
            $reflection = new \ReflectionClass(TaxonomiesResolver::class);
            $prop = $reflection->getProperty('subclassOf');
            expect($prop->getValue())->toBe(\Sloth\Model\Taxonomy::class);
        });

        it('has dir configured', function (): void {
            $reflection = new \ReflectionClass(TaxonomiesResolver::class);
            $prop = $reflection->getProperty('dir');
            expect($prop->getValue())->toBe('Taxonomy');
        });
    });

    describe('ApiControllersResolver', function (): void {
        it('extends ClassResolver', function (): void {
            expect(is_subclass_of(ApiControllersResolver::class, ClassResolver::class))->toBeTrue();
        });

        it('has correct subclassOf configured', function (): void {
            $reflection = new \ReflectionClass(ApiControllersResolver::class);
            $prop = $reflection->getProperty('subclassOf');
            expect($prop->getValue())->toBe(\Sloth\Api\Controller::class);
        });

        it('has dir configured', function (): void {
            $reflection = new \ReflectionClass(ApiControllersResolver::class);
            $prop = $reflection->getProperty('dir');
            expect($prop->getValue())->toBe('Api');
        });
    });

    describe('ModulesResolver', function (): void {
        it('extends ClassResolver', function (): void {
            expect(is_subclass_of(ModulesResolver::class, ClassResolver::class))->toBeTrue();
        });

        it('has correct subclassOf configured', function (): void {
            $reflection = new \ReflectionClass(ModulesResolver::class);
            $prop = $reflection->getProperty('subclassOf');
            expect($prop->getValue())->toBe(\Sloth\Module\Module::class);
        });

        it('has dir configured', function (): void {
            $reflection = new \ReflectionClass(ModulesResolver::class);
            $prop = $reflection->getProperty('dir');
            expect($prop->getValue())->toBe('Module');
        });
    });
});
