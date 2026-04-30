<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Registrar;

use PHPUnit\Framework\TestCase;
use Sloth\Model\Manifest\TaxonomyManifestBuilder;
use Sloth\Model\Registrar\TaxonomyRegistrar;

/**
 * Unit tests for the TaxonomyRegistrar class.
 */
describe('TaxonomyRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated with a builder', function (): void {
            $builder = $this->createMock(TaxonomyManifestBuilder::class);
            $registrar = new TaxonomyRegistrar($builder);
            expect($registrar)->toBeInstanceOf(TaxonomyRegistrar::class);
        });
    });

    describe('register()', function (): void {
        it('method exists', function (): void {
            $builder = $this->createMock(TaxonomyManifestBuilder::class);
            $registrar = new TaxonomyRegistrar($builder);
            expect(method_exists($registrar, 'register'))->toBeTrue();
        });
    });

    describe('addMetaBoxes()', function (): void {
        it('method exists', function (): void {
            $builder = $this->createMock(TaxonomyManifestBuilder::class);
            $registrar = new TaxonomyRegistrar($builder);
            expect(method_exists($registrar, 'addMetaBoxes'))->toBeTrue();
        });
    });
});
