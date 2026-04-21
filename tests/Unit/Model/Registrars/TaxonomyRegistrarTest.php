<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model;

use Sloth\Model\Registrars\TaxonomyRegistrar;

/**
 * Unit tests for the TaxonomyRegistrar class.
 */
describe('TaxonomyRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $registrar = new TaxonomyRegistrar();
            expect($registrar)->toBeInstanceOf(TaxonomyRegistrar::class);
        });
    });

    describe('init()', function (): void {
        it('method exists', function (): void {
            $registrar = new TaxonomyRegistrar();
            expect(method_exists($registrar, 'init'))->toBeTrue();
        });
    });

    describe('getTaxonomies()', function (): void {
        it('method exists and returns array', function (): void {
            $registrar = new TaxonomyRegistrar();
            expect(method_exists($registrar, 'getTaxonomies'))->toBeTrue();
            expect($registrar->getTaxonomies())->toBeArray();
        });
    });
});
