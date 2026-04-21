<?php

declare(strict_types=1);

namespace Sloth\Tests\Unit\Model;

use Sloth\Model\Registrars\MenuRegistrar;

/**
 * Unit tests for the MenuRegistrar class.
 */
describe('MenuRegistrar', function (): void {
    describe('Construction', function (): void {
        it('can be instantiated', function (): void {
            $registrar = new MenuRegistrar();
            expect($registrar)->toBeInstanceOf(MenuRegistrar::class);
        });
    });

    describe('init()', function (): void {
        it('method exists', function (): void {
            $registrar = new MenuRegistrar();
            expect(method_exists($registrar, 'init'))->toBeTrue();
        });
    });
});
