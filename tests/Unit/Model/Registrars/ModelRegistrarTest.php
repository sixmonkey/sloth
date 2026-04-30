<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model\Registrar;

use PHPUnit\Framework\TestCase;
use Sloth\Model\Manifest\ModelManifestBuilder;
use Sloth\Model\Registrar\ModelRegistrar;

/**
 * Unit tests for the ModelRegistrar class.
 */
describe('ModelRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated with a builder', function (): void {
            $builder = $this->createMock(ModelManifestBuilder::class);
            $registrar = new ModelRegistrar($builder);
            expect($registrar)->toBeInstanceOf(ModelRegistrar::class);
        });
    });

    describe('register()', function (): void {
        it('method exists', function (): void {
            $builder = $this->createMock(ModelManifestBuilder::class);
            $registrar = new ModelRegistrar($builder);
            expect(method_exists($registrar, 'register'))->toBeTrue();
        });
    });
});
