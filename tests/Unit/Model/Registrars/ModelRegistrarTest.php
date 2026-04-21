<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model;

use Sloth\Model\Registrars\ModelRegistrar;

/**
 * Unit tests for the ModelRegistrar class.
 */
describe('ModelRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $registrar = new ModelRegistrar();
            expect($registrar)->toBeInstanceOf(ModelRegistrar::class);
        });
    });

    describe('init()', function (): void {
        it('method exists', function (): void {
            $registrar = new ModelRegistrar();
            expect(method_exists($registrar, 'init'))->toBeTrue();
        });
    });
});
