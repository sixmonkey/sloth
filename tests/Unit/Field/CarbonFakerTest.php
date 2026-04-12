<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Field;

use Sloth\Field\CarbonFaker;

describe('CarbonFaker', function (): void {
    describe('__call()', function (): void {
        it('returns empty string for any method call', function (): void {
            $faker = new CarbonFaker();
            expect($faker->format('Y-m-d'))->toBe('');
            expect($faker->toDateString())->toBe('');
            expect($faker->diffForHumans())->toBe('');
        });
    });
});